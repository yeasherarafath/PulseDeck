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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

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

    public function showService(StatusService $service): View
    {
        $this->ensurePublicEnabled();

        abort_unless($service->is_public, 404);

        return view('status.service', $this->public->servicePayload($service));
    }

    public function showIncident(StatusIncident $incident): View
    {
        $this->ensurePublicEnabled();

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

    public function unsubscribe(string $token): RedirectResponse
    {
        // Persistent token: works long after the one-time verify link is consumed.
        StatusSubscriber::where('unsubscribe_token', $token)->delete();

        return redirect()->route('status.index')
            ->with('status', 'You have been unsubscribed.');
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

        $color = $colors[$payload['status']] ?? $colors['unknown'];
        $label = htmlspecialchars($payload['status_label'], ENT_QUOTES);

        return response(
            '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="28" role="img" aria-label="'.$label.'">'
            .'<rect width="220" height="28" rx="6" fill="'.$color.'"/>'
            .'<text x="110" y="18" text-anchor="middle" fill="#fff" font-family="sans-serif" font-size="12">'.$label.'</text>'
            .'</svg>',
            200,
            ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=30']
        );
    }
}
