<?php

namespace App\Http\Controllers\Status;

use App\Enums\Status\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Mail\StatusAlertMail;
use App\Models\Status\StatusIncident;
use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
use App\Models\Status\StatusSubscriber;
use App\Services\Status\PublicStatusService;
use App\Services\Status\StatusMailConfig;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class StatusPageController extends Controller
{
    public function __construct(private PublicStatusService $public)
    {
        //
    }

    public function index(): View
    {
        $this->ensurePublicEnabled();

        $payload = $this->public->payload();

        return view('status.index', $payload);
    }

    public function showService(StatusService $service, Request $request): View
    {
        $this->ensurePublicEnabled();

        abort_unless($service->is_public, 404);

        $payload = $this->public->servicePayload($service);
        $payload['day_detail'] = $this->dayDetail($service, $request, (int) $payload['window_days']);

        return view('status.service', $payload);
    }

    /**
     * Validate the ?date= drill-down param against the displayed window.
     * Out-of-range or malformed dates are a 404, not a silent ignore.
     */
    private function dayDetail(StatusService $service, Request $request, int $window): ?array
    {
        $raw = $request->query('date');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', trim($raw));
        } catch (Throwable) {
            abort(404);
        }

        $date = $parsed->toDateString();

        if ($date !== trim($raw) || $parsed->isFuture() || $date < now()->subDays($window - 1)->toDateString()) {
            abort(404);
        }

        return $this->public->dayFailures($service, $date);
    }

    public function showIncident(StatusIncident $incident): View
    {
        $this->ensurePublicEnabled();

        abort_unless($incident->isVisibleToPublic(), 404);

        return view('status.incident', [
            'incident' => $incident->load(['service', 'updates']),
        ]);
    }

    /**
     * Lightweight polling payload (same cached builder as the page).
     */
    public function refresh(): JsonResponse
    {
        $this->ensurePublicEnabled();

        $payload = $this->public->payload();

        $services = [];

        foreach ($payload['groups'] as $group) {
            foreach ($group['services'] as $service) {
                $services[] = [
                    'slug' => $service['slug'],
                    'status' => $service['status'],
                    'label' => ServiceStatus::tryFrom($service['status'])?->label() ?? $service['status'],
                ];
            }
        }

        return response()->json([
            'status' => $payload['status'],
            'status_label' => $payload['status_label'],
            'updated_at' => $payload['updated_at'],
            'updated_human' => $payload['updated_human'],
            'server_time' => $payload['server_time'],
            'services' => $services,
        ]);
    }

    private function ensurePublicEnabled(): void
    {
        abort_unless((bool) StatusSetting::get('public_page_enabled', true), 404);
    }

    public function subscribe(Request $request): RedirectResponse
    {
        abort_unless((bool) StatusSetting::get('subscriptions_enabled', true), 404);

        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $subscriber = StatusSubscriber::firstOrCreate(
            ['email' => strtolower(trim($validated['email']))],
            ['verification_token' => Str::random(48), 'unsubscribe_token' => Str::random(48), 'is_active' => true],
        );

        if ($subscriber->wasRecentlyCreated && StatusMailConfig::isConfigured()) {
            StatusMailConfig::apply();

            Mail::to($subscriber->email)->send(new StatusAlertMail(
                subjectLine: 'Confirm your status subscription',
                lines: ['Please confirm you want incident emails from '.setting('app_name', config('app.name')).'.'],
                actionUrl: route('status.verify', $subscriber->verification_token),
                eventLabel: 'Subscription',
            ));
        }

        return redirect()->route('status.index')
            ->with('status', $subscriber->verified_at
                ? 'You are already subscribed.'
                : 'Please check your email to confirm the subscription.');
    }

    public function verify(string $token): RedirectResponse
    {
        $subscriber = StatusSubscriber::where('verification_token', $token)->firstOrFail();

        $subscriber->forceFill([
            'verified_at' => now(),
            'verification_token' => null,
            'is_active' => true,
        ])->save();

        return redirect()->route('status.index')
            ->with('status', 'Subscription confirmed. You will receive incident emails.');
    }

    /**
     * GET only renders a confirmation: mail scanners and link prefetchers
     * must never unsubscribe anyone by merely following the link.
     */
    public function confirmUnsubscribe(string $token): View
    {
        abort_unless(StatusSubscriber::where('unsubscribe_token', $token)->exists(), 404);

        return view('status.unsubscribe', ['token' => $token]);
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        // Persistent token: works long after the one-time verify link is consumed.
        $deleted = StatusSubscriber::where('unsubscribe_token', $token)->delete();

        return redirect()->route('status.index')
            ->with('status', $deleted > 0 ? 'You have been unsubscribed.' : 'This unsubscribe link is no longer valid.');
    }

    public function badge(): Response
    {
        abort_unless((bool) StatusSetting::get('badge_enabled', true), 404);

        $payload = $this->public->payload();

        $colors = [
            'operational' => '#2f9e44',
            'degraded' => '#e67700',
            'partial_outage' => '#d9480f',
            'major_outage' => '#e03131',
            'maintenance' => '#1971c2',
            'unknown' => '#868e96',
        ];

        // Short, badge-sized wording (the full sentence stays in aria-label).
        $short = [
            'operational' => 'operational',
            'degraded' => 'degraded',
            'partial_outage' => 'partial outage',
            'major_outage' => 'major outage',
            'maintenance' => 'maintenance',
            'unknown' => 'unknown',
        ];

        $status = $payload['status'];
        $color = $colors[$status] ?? $colors['unknown'];
        $message = $short[$status] ?? $short['unknown'];
        $siteName = (string) setting('app_name', config('app.name', 'Status'));
        $title = htmlspecialchars($siteName.' status: '.$message, ENT_QUOTES);
        $aria = htmlspecialchars('status: '.$payload['status_label'], ENT_QUOTES);

        // Shields-style two-segment badge, sized from the 11px Verdana metrics.
        $leftWidth = (int) round(strlen('status') * 6 + 12);
        $rightWidth = (int) round(strlen($message) * 6 + 30);
        $width = $leftWidth + $rightWidth;
        $dotCx = $leftWidth + 11;
        $messageX = $leftWidth + 19 + (strlen($message) * 6) / 2;

        return response(
            '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="20" role="img" aria-label="'.$aria.'">'
            .'<title>'.$title.'</title>'
            .'<linearGradient id="s" x2="0" y2="100%"><stop offset="0" stop-color="#fff" stop-opacity=".15"/><stop offset="1" stop-opacity="0"/></linearGradient>'
            .'<clipPath id="r"><rect width="'.$width.'" height="20" rx="3"/></clipPath>'
            .'<g clip-path="url(#r)"><rect width="'.$leftWidth.'" height="20" fill="#555"/>'
            .'<rect x="'.$leftWidth.'" width="'.$rightWidth.'" height="20" fill="'.$color.'"/>'
            .'<rect width="'.$width.'" height="20" fill="url(#s)"/></g>'
            .'<g font-family="Verdana,DejaVu Sans,sans-serif" font-size="11">'
            .'<text x="'.($leftWidth / 2).'" y="15" fill="#010101" fill-opacity=".3" text-anchor="middle">status</text>'
            .'<text x="'.($leftWidth / 2).'" y="14" fill="#fff" text-anchor="middle">status</text>'
            .'<circle cx="'.$dotCx.'" cy="10" r="4" fill="#fff" opacity=".9">'
            .'<animate attributeName="opacity" values="1;.35;1" dur="1.6s" repeatCount="indefinite"/></circle>'
            .'<text x="'.$messageX.'" y="15" fill="#010101" fill-opacity=".3" text-anchor="middle">'.htmlspecialchars($message, ENT_QUOTES).'</text>'
            .'<text x="'.$messageX.'" y="14" fill="#fff" text-anchor="middle">'.htmlspecialchars($message, ENT_QUOTES).'</text>'
            .'</g></svg>',
            200,
            ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=30']
        );
    }
}
