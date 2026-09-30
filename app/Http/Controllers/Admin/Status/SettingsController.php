<?php

namespace App\Http\Controllers\Admin\Status;

use App\Enums\Status\SettingGroup;
use App\Enums\Status\SettingType;
use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusSetting;
use App\Rules\AllowedMonitorUrl;
use App\Services\Status\SsrfGuard;
use App\Services\Status\StatusMailConfig;
use App\Services\Status\SvgSanitizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    use AuthorizesRequests;

    /**
     * Branding keys edited as image uploads (drag-drop + preview),
     * stored on the public disk under branding/.
     *
     * @var list<string>
     */
    public const BRANDING_FILES = ['logo_path', 'logo_dark_path', 'favicon_path', 'og_image_path'];

    public function index(): View
    {
        $this->authorize('status.settings.manage');

        $grouped = [];

        foreach (SettingGroup::cases() as $group) {
            $grouped[$group->value] = [
                'label' => $group->label(),
                'settings' => StatusSetting::forGroup($group)->get(),
            ];
        }

        return view('admin.status.settings.index', [
            'grouped' => $grouped,
            'mailConfigured' => StatusMailConfig::isConfigured(),
            'timezones' => self::groupedTimezones(),
        ]);
    }

    /**
     * PHP identifiers grouped by region for the timezone dropdown.
     *
     * @return array<string, list<string>>
     */
    public static function groupedTimezones(): array
    {
        $grouped = [];

        foreach (timezone_identifiers_list() as $zone) {
            $parts = explode('/', $zone, 2);
            $grouped[count($parts) === 2 ? $parts[0] : 'Other'][] = $zone;
        }

        ksort($grouped);

        return $grouped;
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('status.settings.manage');

        $rows = StatusSetting::all()->keyBy('key');
        $rules = [];

        foreach ($rows as $key => $row) {
            $rules["settings.{$key}"] = match ($row->type) {
                SettingType::Integer => ['nullable', 'integer'],
                SettingType::Boolean => ['sometimes', 'boolean'],
                default => ['nullable', 'string', 'max:2048'],
            };
        }

        $rules['settings.admin_prefix'][] = 'regex:/^[a-z0-9\-\/]+$/i';
        $rules['settings.base_url'][] = 'url';
        $rules['settings.webhook_default_url'][] = 'url';
        if (! config('status.webhook_allow_private')) {
            $rules['settings.webhook_default_url'][] = new AllowedMonitorUrl;
        }
        $rules['settings.mail_from_address'][] = 'email';
        $rules['settings.contact_email'][] = 'email';
        $rules['settings.mail_port'][] = 'min:1';
        $rules['settings.mail_port'][] = 'max:65535';
        $rules['settings.theme_default'][] = 'in:light,dark';
        $rules['settings.timezone'][] = 'timezone';
        $rules['settings.raw_checks_retention_days'][] = 'min:1';
        $rules['settings.daily_stats_retention_days'][] = 'min:1';
        $rules['settings.audit_retention_days'][] = 'min:1';
        // Free-form default (60s–1yr): presets live in CheckInterval, but
        // admins may type any custom value — the service form validates it.
        $rules['settings.default_check_interval'][] = 'min:60';
        $rules['settings.default_check_interval'][] = 'max:31536000';
        $rules['settings.default_timeout'][] = 'min:1';
        $rules['settings.default_timeout'][] = 'max:60';
        $rules['settings.default_connect_timeout'][] = 'min:1';
        $rules['settings.default_connect_timeout'][] = 'max:60';
        $rules['settings.mail_mailer'][] = 'in:smtp,sendmail,log';
        $rules['settings.mail_encryption'][] = 'in:tls,ssl,none';
        $rules['branding.*'] = ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp,ico', 'max:2048'];
        $rules['remove_branding.*'] = ['sometimes', 'boolean'];
        $rules['clear_secrets.*'] = ['sometimes', 'boolean'];
        $rules['settings.app_name'][] = 'max:100';
        $rules['settings.meta_keywords'][] = 'max:255';
        $rules['settings.meta_robots'][] = 'max:100';

        $validated = $request->validate($rules);
        $input = $validated['settings'] ?? [];

        $timeout = (int) ($input['default_timeout'] ?? $rows['default_timeout']->value ?? 15);
        $connectTimeout = (int) ($input['default_connect_timeout'] ?? $rows['default_connect_timeout']->value ?? 5);

        if ($connectTimeout > $timeout) {
            throw ValidationException::withMessages([
                'settings.default_connect_timeout' => 'The default connect timeout may not exceed the default timeout.',
            ]);
        }

        // Full-form posts (the settings UI sends settings_form=1) treat
        // missing checkboxes as OFF. Partial payloads (API/tests) only touch
        // submitted keys and never wipe the rest.
        $fullForm = (bool) $request->input('settings_form', false);
        $changed = [];

        foreach ($rows as $key => $row) {
            if (in_array($key, self::BRANDING_FILES, true)) {
                continue; // Handled as uploads below.
            }

            if ($row->type === SettingType::Boolean) {
                if (! array_key_exists($key, $input) && ! $fullForm) {
                    continue;
                }

                $value = ! empty($input[$key]);
            } elseif (! array_key_exists($key, $input)) {
                continue;
            } else {
                $value = $input[$key];
            }

            // Blank secret fields keep the stored value (forms never prefill
            // them); an explicit "clear" tick removes it.
            if ($row->is_encrypted && ($value === null || $value === '')) {
                if ($request->boolean("clear_secrets.{$key}")) {
                    StatusSetting::set($key, null, $request->user()->id);
                    $changed[] = $key;
                }

                continue;
            }

            if ($key === 'admin_prefix') {
                $value = trim((string) $value, '/') ?: 'admin';
            }

            StatusSetting::set($key, $value, $request->user()->id);
            $changed[] = $key;
        }

        $changed = array_merge($changed, $this->handleBrandingUploads($request));

        StatusAuditLog::record('settings.saved', null, null, ['keys' => $changed]);

        return redirect()->route('admin.status.settings')->with('status', 'Settings saved.');
    }

    /**
     * Store/remove uploaded branding images. Returns changed keys.
     *
     * Every upload is validated/sanitized BEFORE anything is stored or
     * deleted, so a rejected file never leaves half-applied changes.
     *
     * @return list<string>
     */
    private function handleBrandingUploads(Request $request): array
    {
        $changed = [];
        $prepared = [];

        foreach (self::BRANDING_FILES as $key) {
            $file = $request->file("branding.{$key}");

            if ($request->boolean("remove_branding.{$key}") || ! $file || ! $file->isValid()) {
                continue;
            }

            // SVGs are served raw: allowlist-sanitize so a logo can never
            // become stored XSS or leak files via XML entities.
            if (strtolower($file->getClientOriginalExtension()) === 'svg') {
                $sanitized = self::sanitizeSvg((string) file_get_contents($file->getRealPath()));

                if ($sanitized === null) {
                    throw ValidationException::withMessages([
                        "branding.{$key}" => 'The SVG could not be read safely and was rejected.',
                    ]);
                }

                $prepared[$key] = $sanitized;
            }
        }

        foreach (self::BRANDING_FILES as $key) {
            $row = StatusSetting::where('key', $key)->first();

            if (! $row) {
                continue;
            }

            if ($request->boolean("remove_branding.{$key}")) {
                $this->deleteBrandingFile($row->value);
                StatusSetting::set($key, null, $request->user()->id);
                $changed[] = $key;

                continue;
            }

            $file = $request->file("branding.{$key}");

            if (! $file || ! $file->isValid()) {
                continue;
            }

            if (isset($prepared[$key])) {
                $path = 'branding/'.Str::random(40).'.svg';

                Storage::disk('public')->put($path, $prepared[$key]);
            } else {
                $path = $file->store('branding', 'public');
            }

            $this->deleteBrandingFile($row->value);
            StatusSetting::set($key, $path, $request->user()->id);
            $changed[] = $key;
        }

        return $changed;
    }

    /**
     * Clean SVG markup (see SvgSanitizer). Returns null when unsafe/unparseable.
     */
    public static function sanitizeSvg(string $xml): ?string
    {
        return SvgSanitizer::sanitize($xml);
    }

    private function deleteBrandingFile(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }

    public function clearCache(): RedirectResponse
    {
        $this->authorize('status.settings.manage');

        try {
            Artisan::call('optimize:clear');
            $output = trim(Artisan::output()) ?: 'All caches cleared.';
        } catch (\Throwable $exception) {
            return redirect()->route('admin.status.settings')
                ->with('error', 'Cache clear failed: '.mb_substr($exception->getMessage(), 0, 300));
        }

        StatusAuditLog::record('settings.cache-cleared', null, null, null);

        return redirect()->route('admin.status.settings')
            ->with('status', 'Caches cleared. '.mb_substr($output, 0, 300));
    }

    public function queueWork(): RedirectResponse
    {
        $this->authorize('status.settings.manage');

        try {
            set_time_limit(90);
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--timeout' => 60,
                '--tries' => 1,
                '--max-time' => 50,
            ]);
            $output = trim(Artisan::output()) ?: 'Queue drained — no pending jobs.';
        } catch (\Throwable $exception) {
            return redirect()->route('admin.status.settings')
                ->with('error', 'Queue run failed: '.mb_substr($exception->getMessage(), 0, 300));
        }

        StatusAuditLog::record('settings.queue-worked', null, null, null);

        return redirect()->route('admin.status.settings')
            ->with('status', 'Queue processed. '.mb_substr($output, 0, 300));
    }

    public function testMail(Request $request): RedirectResponse
    {
        $this->authorize('status.settings.manage');

        $validated = $request->validate(['email' => ['required', 'email']]);

        StatusMailConfig::apply();

        try {
            Mail::raw(
                'This is a test email from '.setting('app_name', config('app.name')).'. Monitoring alerts are working.',
                fn ($message) => $message->to($validated['email'])->subject('Test email from '.setting('app_name', config('app.name')))
            );
        } catch (\Throwable $exception) {
            return redirect()->route('admin.status.settings')
                ->with('error', 'Test email failed: '.mb_substr($exception->getMessage(), 0, 300));
        }

        return redirect()->route('admin.status.settings')->with('status', "Test email sent to {$validated['email']}.");
    }

    public function testWebhook(Request $request): RedirectResponse
    {
        $this->authorize('status.settings.manage');

        $validated = $request->validate(['url' => ['nullable', 'url', 'max:2048']]);

        $endpoint = $validated['url'] ?? setting('webhook_default_url');

        if (! $endpoint) {
            return redirect()->route('admin.status.settings')->with('error', 'No webhook URL to test.');
        }

        try {
            $options = ['allow_redirects' => false];

            if (! config('status.webhook_allow_private')) {
                $options += SsrfGuard::pinOptions($endpoint, app(SsrfGuard::class)->assertSafeUrl($endpoint));
            }

            Http::timeout((int) setting('webhook_timeout', 10))->withOptions($options)->post($endpoint, [
                'event' => 'test',
                'subject' => 'Webhook test from '.setting('app_name', config('app.name')),
                'sent_at' => now()->toIso8601String(),
            ])->throw();
        } catch (\Throwable $exception) {
            return redirect()->route('admin.status.settings')
                ->with('error', 'Test webhook failed: '.mb_substr($exception->getMessage(), 0, 300));
        }

        return redirect()->route('admin.status.settings')->with('status', 'Test webhook delivered.');
    }
}
