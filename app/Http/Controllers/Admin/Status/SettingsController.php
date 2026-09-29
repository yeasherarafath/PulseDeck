<?php

namespace App\Http\Controllers\Admin\Status;

use App\Enums\Status\SettingGroup;
use App\Enums\Status\SettingType;
use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusSetting;
use App\Services\Status\StatusMailConfig;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SettingsController extends Controller
{
    use AuthorizesRequests;

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
        ]);
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
        $rules['settings.mail_from_address'][] = 'email';
        $rules['settings.contact_email'][] = 'email';
        $rules['settings.mail_port'][] = 'integer|min:1|max:65535';
        $rules['settings.theme_default'][] = 'in:light,dark';
        $rules['settings.mail_mailer'][] = 'in:smtp,sendmail,log';
        $rules['settings.mail_encryption'][] = 'in:tls,ssl,none';

        $validated = $request->validate($rules);
        $input = $validated['settings'] ?? [];
        $changed = [];

        foreach ($rows as $key => $row) {
            if ($row->type === SettingType::Boolean) {
                $value = ! empty($input[$key]);
            } else {
                $value = $input[$key] ?? null;
            }

            // Blank secret fields keep the stored value (forms never prefill them).
            if ($row->is_encrypted && ($value === null || $value === '')) {
                continue;
            }

            if ($key === 'admin_prefix') {
                $value = trim((string) $value, '/') ?: 'admin';
            }

            StatusSetting::set($key, $value, $request->user()->id);
            $changed[] = $key;
        }

        StatusAuditLog::record('settings.saved', null, null, ['keys' => $changed]);

        return redirect()->route('admin.status.settings')->with('status', 'Settings saved.');
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
            Http::timeout((int) setting('webhook_timeout', 10))->post($endpoint, [
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
