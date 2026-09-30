<?php

namespace App\Jobs\Status;

use App\Enums\Status\NotificationChannelType;
use App\Enums\Status\NotificationEvent;
use App\Mail\StatusAlertMail;
use App\Models\Status\StatusNotificationChannel;
use App\Models\Status\StatusNotificationDelivery;
use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
use App\Services\Status\SsrfGuard;
use App\Services\Status\StatusMailConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * One queued delivery to one channel. Failures are logged (redacted),
 * never retried aggressively to avoid notification storms.
 */
class SendStatusNotification implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [30, 120];

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $to  Recipient emails (mail channels).
     * @param  array<string, string>  $unsubscribeTokens  Email => token for per-recipient footers.
     */
    public function __construct(
        public int $channelId,
        public string $event,
        public ?int $serviceId,
        public string $subject,
        public array $lines = [],
        public ?string $url = null,
        public array $to = [],
        public array $unsubscribeTokens = [],
    ) {
        //
    }

    public function handle(): void
    {
        $channel = StatusNotificationChannel::find($this->channelId);

        if (! $channel || ! $channel->is_active) {
            $this->record('skipped', 'Channel missing or inactive.', count($this->to));

            return;
        }

        try {
            match ($channel->type) {
                NotificationChannelType::Mail => $this->sendMail(),
                NotificationChannelType::Webhook => $this->sendWebhook($channel),
                default => $this->record('skipped', 'Channel type not implemented.', count($this->to)),
            };
        } catch (\Throwable $exception) {
            $error = mb_substr($exception->getMessage(), 0, 500);

            Log::warning('Status notification failed', [
                'channel_id' => $channel->id,
                'channel_type' => $channel->type->value,
                'event' => $this->event,
                'error' => $error,
            ]);

            $this->record('failed', $error);
        }
    }

    private function sendMail(): void
    {
        if ($this->to === []) {
            $this->record('skipped', 'No recipients.', 0);

            return;
        }

        StatusMailConfig::apply();

        $event = NotificationEvent::tryFrom($this->event);
        $sent = 0;

        // A retry re-runs the whole job: remember who was already mailed so
        // a mid-list failure never resends to earlier recipients.
        $cacheKey = 'status:notification-sent:'.($this->job?->uuid() ?? spl_object_id($this));
        $done = Cache::get($cacheKey, []);

        // Subscribers get individual mails with their own unsubscribe link;
        // plain channel recipients share one bulk mail without a footer.
        $bulk = array_values(array_diff($this->to, array_keys($this->unsubscribeTokens)));

        if ($bulk !== [] && ! in_array('*bulk', $done, true)) {
            Mail::to($bulk)->send($this->mailable($event, null));
            $done[] = '*bulk';
            Cache::put($cacheKey, $done, now()->addDay());
        }

        $sent += count($bulk);

        foreach ($this->unsubscribeTokens as $email => $token) {
            if (! in_array($email, $this->to, true)) {
                continue;
            }

            if (! in_array($email, $done, true)) {
                Mail::to($email)->send($this->mailable($event, route('status.unsubscribe', $token)));
                $done[] = $email;
                Cache::put($cacheKey, $done, now()->addDay());
            }

            $sent++;
        }

        Cache::forget($cacheKey);

        $this->record('sent', null, $sent);
    }

    private function mailable(?NotificationEvent $event, ?string $unsubscribeUrl): StatusAlertMail
    {
        return new StatusAlertMail(
            subjectLine: $this->subject,
            lines: $this->lines,
            actionUrl: $this->url,
            eventLabel: $event?->label() ?? $this->event,
            unsubscribeUrl: $unsubscribeUrl,
        );
    }

    private function sendWebhook(StatusNotificationChannel $channel): void
    {
        $config = $channel->config ?? [];
        $endpoint = $config['url'] ?? StatusSetting::get('webhook_default_url');

        if (! is_string($endpoint) || trim($endpoint) === '') {
            Log::warning('Status webhook skipped: no endpoint configured', ['channel_id' => $channel->id]);

            return;
        }

        $service = $this->serviceId !== null ? StatusService::find($this->serviceId) : null;

        $payload = [
            'event' => $this->event,
            'subject' => $this->subject,
            'lines' => $this->lines,
            'url' => $this->url,
            'service_id' => $this->serviceId,
            'service_name' => $service?->name,
            'service_slug' => $service?->slug,
            'sent_at' => now()->toIso8601String(),
        ];

        $headers = [];

        $secret = $config['secret'] ?? StatusSetting::get('webhook_secret');

        if (is_string($secret) && $secret !== '') {
            $headers['X-Status-Signature'] = hash_hmac('sha256', json_encode($payload) ?: '', $secret);
        }

        $options = ['allow_redirects' => false];

        if (! config('status.webhook_allow_private')) {
            $options += SsrfGuard::pinOptions($endpoint, app(SsrfGuard::class)->assertSafeUrl($endpoint));
        }

        Http::timeout((int) StatusSetting::get('webhook_timeout', 10))
            ->withOptions($options)
            ->withHeaders($headers)
            ->post($endpoint, $payload)
            ->throw();

        $this->record('sent', null, 1);
    }

    private function record(string $status, ?string $error = null, int $recipientCount = 0): void
    {
        StatusNotificationDelivery::create([
            'channel_id' => $this->channelId,
            'event' => $this->event,
            'service_id' => $this->serviceId,
            'recipient_count' => $recipientCount,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
