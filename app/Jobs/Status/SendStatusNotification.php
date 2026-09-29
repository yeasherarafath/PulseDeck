<?php

namespace App\Jobs\Status;

use App\Enums\Status\NotificationChannelType;
use App\Enums\Status\NotificationEvent;
use App\Mail\StatusAlertMail;
use App\Models\Status\StatusNotificationChannel;
use App\Models\Status\StatusSetting;
use App\Services\Status\StatusMailConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
     */
    public function __construct(
        public int $channelId,
        public string $event,
        public ?int $serviceId,
        public string $subject,
        public array $lines = [],
        public ?string $url = null,
        public array $to = [],
    ) {
        //
    }

    public function handle(): void
    {
        $channel = StatusNotificationChannel::find($this->channelId);

        if (! $channel || ! $channel->is_active) {
            return;
        }

        try {
            match ($channel->type) {
                NotificationChannelType::Mail => $this->sendMail(),
                NotificationChannelType::Webhook => $this->sendWebhook($channel),
                default => null,
            };
        } catch (\Throwable $exception) {
            Log::warning('Status notification failed', [
                'channel_id' => $channel->id,
                'channel_type' => $channel->type->value,
                'event' => $this->event,
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);
        }
    }

    private function sendMail(): void
    {
        if ($this->to === []) {
            return;
        }

        StatusMailConfig::apply();

        $event = NotificationEvent::tryFrom($this->event);

        Mail::to($this->to)->send(new StatusAlertMail(
            subjectLine: $this->subject,
            lines: $this->lines,
            actionUrl: $this->url,
            eventLabel: $event?->label() ?? $this->event,
        ));
    }

    private function sendWebhook(StatusNotificationChannel $channel): void
    {
        $config = $channel->config ?? [];
        $endpoint = $config['url'] ?? StatusSetting::get('webhook_default_url');

        if (! is_string($endpoint) || trim($endpoint) === '') {
            Log::warning('Status webhook skipped: no endpoint configured', ['channel_id' => $channel->id]);

            return;
        }

        $payload = [
            'event' => $this->event,
            'subject' => $this->subject,
            'lines' => $this->lines,
            'url' => $this->url,
            'service_id' => $this->serviceId,
            'sent_at' => now()->toIso8601String(),
        ];

        $headers = [];

        $secret = $config['secret'] ?? StatusSetting::get('webhook_secret');

        if (is_string($secret) && $secret !== '') {
            $headers['X-Status-Signature'] = hash_hmac('sha256', json_encode($payload) ?: '', $secret);
        }

        Http::timeout((int) StatusSetting::get('webhook_timeout', 10))
            ->withHeaders($headers)
            ->post($endpoint, $payload)
            ->throw();
    }
}
