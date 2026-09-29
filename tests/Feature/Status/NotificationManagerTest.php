<?php

namespace Tests\Feature\Status;

use App\Enums\Status\NotificationEvent;
use App\Jobs\Status\SendStatusNotification;
use App\Models\Status\StatusNotificationChannel;
use App\Models\Status\StatusNotificationRule;
use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
use App\Services\Status\NotificationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationManagerTest extends TestCase
{
    use RefreshDatabase;

    private function mailChannel(array $to = ['ops@example.com']): StatusNotificationChannel
    {
        return StatusNotificationChannel::create([
            'name' => 'Ops mail',
            'type' => 'mail',
            'config' => ['to' => $to],
            'is_active' => true,
        ]);
    }

    private function rule(StatusNotificationChannel $channel, NotificationEvent $event, ?int $serviceId = null): StatusNotificationRule
    {
        return StatusNotificationRule::create([
            'channel_id' => $channel->id,
            'service_id' => $serviceId,
            'event' => $event->value,
            'is_active' => true,
        ]);
    }

    private function enableMail(): void
    {
        StatusSetting::updateOrCreate(['key' => 'mail_enabled'], [
            'value' => '1', 'type' => 'boolean', 'group' => 'mail', 'is_encrypted' => false,
        ]);
        StatusSetting::updateOrCreate(['key' => 'mail_mailer'], [
            'value' => 'log', 'type' => 'string', 'group' => 'mail', 'is_encrypted' => false,
        ]);

        Cache::flush();
    }

    private function service(): StatusService
    {
        return StatusService::factory()->create(['url' => 'https://example.com/']);
    }

    private function notify(NotificationEvent $event, ?StatusService $service = null): void
    {
        app(NotificationManager::class)->notify($event, $service, 'Subject', ['line'], 'https://example.com/status');
    }

    /** N-T1: per-service failure flag off suppresses failure mails. */
    public function test_service_failure_flag_off_skips_notification(): void
    {
        Queue::fake();
        $this->enableMail();

        $service = $this->service();
        $service->forceFill(['notify_on_failure' => false])->save();

        $channel = $this->mailChannel();
        $this->rule($channel, NotificationEvent::ServiceFailed);

        $this->notify(NotificationEvent::ServiceFailed, $service);

        Queue::assertNotPushed(SendStatusNotification::class);
    }

    /** N-T2: per-service recovery flag off suppresses recovery mails. */
    public function test_service_recovery_flag_off_skips_notification(): void
    {
        Queue::fake();
        $this->enableMail();

        $service = $this->service();
        $service->forceFill(['notify_on_recovery' => false])->save();

        $channel = $this->mailChannel();
        $this->rule($channel, NotificationEvent::ServiceRecovered);

        $this->notify(NotificationEvent::ServiceRecovered, $service);

        Queue::assertNotPushed(SendStatusNotification::class);
    }

    /** N-T3: flags on + matching rule queues the job. */
    public function test_matching_rule_queues_notification(): void
    {
        Queue::fake();
        $this->enableMail();

        $service = $this->service();
        $channel = $this->mailChannel();
        $this->rule($channel, NotificationEvent::ServiceFailed);

        $this->notify(NotificationEvent::ServiceFailed, $service);

        Queue::assertPushed(SendStatusNotification::class, 1);
    }

    /** N-T4: master switch / event flag / inactive channel suppress. */
    public function test_master_switches_and_inactive_channel_suppress(): void
    {
        Queue::fake();
        $this->enableMail();

        $service = $this->service();
        $channel = $this->mailChannel();
        $this->rule($channel, NotificationEvent::ServiceFailed);

        StatusSetting::updateOrCreate(['key' => 'email_alerts_enabled'], [
            'value' => '0', 'type' => 'boolean', 'group' => 'alerts', 'is_encrypted' => false,
        ]);

        Cache::flush();

        $this->notify(NotificationEvent::ServiceFailed, $service);

        Queue::assertNotPushed(SendStatusNotification::class);

        StatusSetting::where('key', 'email_alerts_enabled')->update(['value' => '1']);

        Cache::flush();

        $channel->forceFill(['is_active' => false])->save();

        $this->notify(NotificationEvent::ServiceFailed, $service);

        Queue::assertNotPushed(SendStatusNotification::class);
    }

    /** N-T5: service-scoped rules only match their service. */
    public function test_service_scoped_rule_matching(): void
    {
        Queue::fake();
        $this->enableMail();

        $serviceA = $this->service();
        $serviceB = $this->service();
        $channel = $this->mailChannel();
        $this->rule($channel, NotificationEvent::ServiceFailed, $serviceA->id);

        $this->notify(NotificationEvent::ServiceFailed, $serviceB);

        Queue::assertNotPushed(SendStatusNotification::class);

        $this->notify(NotificationEvent::ServiceFailed, $serviceA);

        Queue::assertPushed(SendStatusNotification::class, 1);
    }

    /** N-T14: inactive channel jobs are skipped without error. */
    public function test_inactive_channel_sends_nothing(): void
    {
        Queue::fake();

        $channel = $this->mailChannel();
        $channel->forceFill(['is_active' => false])->save();
        $this->rule($channel, NotificationEvent::IncidentCreated);

        $this->notify(NotificationEvent::IncidentCreated, null);

        Queue::assertNotPushed(SendStatusNotification::class);
    }
}
