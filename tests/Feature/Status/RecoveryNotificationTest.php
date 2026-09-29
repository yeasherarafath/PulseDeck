<?php

namespace Tests\Feature\Status;

use App\Enums\Status\NotificationEvent;
use App\Events\Status\ServiceBecameDegraded;
use App\Jobs\Status\CheckService;
use App\Jobs\Status\SendStatusNotification;
use App\Models\Status\StatusNotificationChannel;
use App\Models\Status\StatusNotificationRule;
use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
use App\Services\Status\AssertionEngine;
use App\Services\Status\HttpChecker;
use App\Services\Status\IncidentManager;
use App\Services\Status\MaintenanceManager;
use App\Services\Status\RequestBuilder;
use App\Services\Status\StatusCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecoveryNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function mailChannel(): StatusNotificationChannel
    {
        return StatusNotificationChannel::create([
            'name' => 'Ops mail',
            'type' => 'mail',
            'config' => ['to' => ['ops@example.com']],
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

    /** N-T10: one auto-recovery produces exactly the resolution mail. */
    public function test_auto_recovery_sends_single_resolution_mail(): void
    {
        Queue::fake();
        $this->enableMail();

        $service = StatusService::factory()->create([
            'url' => 'https://example.com/',
            'failure_threshold' => 1,
            'recovery_threshold' => 1,
        ]);

        $channel = $this->mailChannel();

        foreach (NotificationEvent::cases() as $event) {
            StatusNotificationRule::create([
                'channel_id' => $channel->id,
                'service_id' => null,
                'event' => $event->value,
                'is_active' => true,
            ]);
        }

        Http::fakeSequence()
            ->push('boom', 500)
            ->push('{"status":"ok"}', 200);

        app(CheckService::class, ['serviceId' => $service->id])->handle(
            app(RequestBuilder::class),
            app(HttpChecker::class),
            app(AssertionEngine::class),
            app(StatusCalculator::class),
            app(IncidentManager::class),
            app(MaintenanceManager::class),
        );

        app(CheckService::class, ['serviceId' => $service->id])->handle(
            app(RequestBuilder::class),
            app(HttpChecker::class),
            app(AssertionEngine::class),
            app(StatusCalculator::class),
            app(IncidentManager::class),
            app(MaintenanceManager::class),
        );

        // fail + incident.created, then incident.resolved only (no service.recovered duplicate).
        Queue::assertPushed(SendStatusNotification::class, 3);
        Queue::assertPushed(
            SendStatusNotification::class,
            fn ($job) => $job->event === NotificationEvent::IncidentResolved->value
        );
        Queue::assertNotPushed(
            SendStatusNotification::class,
            fn ($job) => $job->event === NotificationEvent::ServiceRecovered->value
        );
    }

    /** N-T11: degraded maps only to degraded rules. */
    public function test_degraded_event_matches_only_degraded_rules(): void
    {
        Queue::fake();
        $this->enableMail();

        $service = StatusService::factory()->create(['url' => 'https://example.com/']);
        $channel = $this->mailChannel();

        StatusNotificationRule::create([
            'channel_id' => $channel->id,
            'service_id' => null,
            'event' => NotificationEvent::ServiceFailed->value,
            'is_active' => true,
        ]);

        ServiceBecameDegraded::dispatch($service);

        Queue::assertNotPushed(SendStatusNotification::class);

        StatusNotificationRule::create([
            'channel_id' => $channel->id,
            'service_id' => null,
            'event' => NotificationEvent::ServiceDegraded->value,
            'is_active' => true,
        ]);

        ServiceBecameDegraded::dispatch($service);

        Queue::assertPushed(
            SendStatusNotification::class,
            fn ($job) => $job->event === NotificationEvent::ServiceDegraded->value
        );
    }
}
