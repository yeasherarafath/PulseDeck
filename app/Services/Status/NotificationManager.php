<?php

namespace App\Services\Status;

use App\Enums\Status\NotificationChannelType;
use App\Enums\Status\NotificationEvent;
use App\Jobs\Status\SendStatusNotification;
use App\Models\Status\StatusNotificationChannel;
use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
use App\Models\Status\StatusSubscriber;

/**
 * Generic fan-out: event + service → matching channels → queued sends.
 * Never called from CheckService directly; listeners invoke this.
 */
class NotificationManager
{
    /**
     * @var list<NotificationEvent> Events that public subscribers receive.
     */
    private const SUBSCRIBER_EVENTS = [
        NotificationEvent::ServiceFailed,
        NotificationEvent::ServiceRecovered,
        NotificationEvent::IncidentCreated,
        NotificationEvent::IncidentUpdated,
        NotificationEvent::IncidentResolved,
    ];

    /**
     * @param  list<string>  $lines
     */
    public function notify(NotificationEvent $event, ?StatusService $service, string $subject, array $lines = [], ?string $url = null): void
    {
        if (! StatusSetting::get($event->settingKey(), true)) {
            return;
        }

        $channels = StatusNotificationChannel::where('is_active', true)
            ->whereHas('rules', function ($query) use ($event, $service): void {
                $query->where('is_active', true)
                    ->where('event', $event->value)
                    ->where(function ($query) use ($service): void {
                        $query->whereNull('service_id');

                        if ($service) {
                            $query->orWhere('service_id', $service->id);
                        }
                    });
            })
            ->get();

        if ($channels->isEmpty()) {
            return;
        }

        $mailAllowed = (bool) StatusSetting::get('mail_enabled', false)
            && (bool) StatusSetting::get('email_alerts_enabled', true)
            && StatusMailConfig::isConfigured();

        $webhookAllowed = (bool) StatusSetting::get('webhook_alerts_enabled', true);

        $subscriberEmails = $this->subscriberEmails($event);

        foreach ($channels as $channel) {
            if ($channel->type === NotificationChannelType::Mail && ! $mailAllowed) {
                continue;
            }

            if ($channel->type === NotificationChannelType::Webhook && ! $webhookAllowed) {
                continue;
            }

            if (! $channel->type->implemented()) {
                continue;
            }

            $to = $this->channelRecipients($channel);

            if ($channel->type === NotificationChannelType::Mail) {
                $to = array_values(array_unique(array_merge($to, $subscriberEmails)));
            }

            if ($channel->type === NotificationChannelType::Mail && $to === []) {
                continue;
            }

            SendStatusNotification::dispatch(
                $channel->id,
                $event->value,
                $service?->id,
                $subject,
                array_values($lines),
                $url,
                $to,
            );
        }
    }

    /**
     * @return list<string>
     */
    private function channelRecipients(StatusNotificationChannel $channel): array
    {
        $config = $channel->config ?? [];
        $to = $config['to'] ?? [];

        if (is_string($to)) {
            $to = explode(',', $to);
        }

        return array_values(array_filter(array_map(
            fn ($email) => trim((string) $email),
            is_array($to) ? $to : []
        ), fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL)));
    }

    /**
     * @return list<string>
     */
    private function subscriberEmails(NotificationEvent $event): array
    {
        if (! in_array($event, self::SUBSCRIBER_EVENTS, true)) {
            return [];
        }

        if (! (bool) StatusSetting::get('subscriptions_enabled', true)) {
            return [];
        }

        return StatusSubscriber::where('is_active', true)
            ->whereNotNull('verified_at')
            ->pluck('email')
            ->all();
    }
}
