<?php

namespace Tests\Feature\Status;

use App\Enums\Status\NotificationEvent;
use App\Jobs\Status\SendStatusNotification;
use App\Models\Status\StatusIncident;
use App\Models\Status\StatusNotificationChannel;
use App\Models\Status\StatusNotificationRule;
use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
use App\Models\Status\StatusSubscriber;
use App\Services\Status\NotificationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function incident(?StatusService $service, string $slug): StatusIncident
    {
        return StatusIncident::create([
            'service_id' => $service?->id,
            'title' => $slug,
            'slug' => $slug,
            'status' => 'investigating',
            'impact' => 'major',
            'started_at' => now(),
        ]);
    }

    public function test_private_service_incidents_are_hidden_publicly(): void
    {
        $private = StatusService::factory()->create(['is_public' => false, 'name' => 'Secret Backend']);
        $public = StatusService::factory()->create(['is_public' => true, 'name' => 'Public Site']);

        $hidden = $this->incident($private, 'secret-backend-down');
        $shown = $this->incident($public, 'public-site-down');
        $global = $this->incident(null, 'global-issue');

        $this->get("/status/incidents/{$hidden->slug}")->assertNotFound();
        $this->get("/status/incidents/{$shown->slug}")->assertOk();
        $this->get("/status/incidents/{$global->slug}")->assertOk();

        $slugs = collect($this->getJson('/api/status/incidents')->assertOk()->json('data'))->pluck('slug')->all();

        $this->assertNotContains('secret-backend-down', $slugs);
        $this->assertContains('public-site-down', $slugs);
        $this->assertContains('global-issue', $slugs);

        Cache::flush();
        $this->get('/')->assertDontSee('Secret Backend');
    }

    public function test_subscribers_never_get_mail_about_private_services(): void
    {
        Queue::fake();

        foreach (['mail_enabled' => '1', 'mail_mailer' => 'log'] as $key => $value) {
            StatusSetting::updateOrCreate(['key' => $key], [
                'value' => $value,
                'type' => $key === 'mail_enabled' ? 'boolean' : 'string',
                'group' => 'mail',
                'is_encrypted' => false,
            ]);
        }

        Cache::flush();

        StatusSubscriber::create([
            'email' => 'fan@example.com',
            'verified_at' => now(),
            'is_active' => true,
            'unsubscribe_token' => 'tok',
            'verification_token' => null,
        ]);

        $channel = StatusNotificationChannel::create([
            'name' => 'Mail',
            'type' => 'mail',
            'config' => ['to' => ['ops@example.com']],
            'is_active' => true,
        ]);

        StatusNotificationRule::create([
            'channel_id' => $channel->id,
            'service_id' => null,
            'event' => NotificationEvent::ServiceFailed->value,
            'is_active' => true,
        ]);

        $private = StatusService::factory()->create(['is_public' => false]);
        $public = StatusService::factory()->create(['is_public' => true]);

        $manager = app(NotificationManager::class);
        $manager->notify(NotificationEvent::ServiceFailed, $private, 'down', [], null);
        $manager->notify(NotificationEvent::ServiceFailed, $public, 'down', [], null);

        $jobs = Queue::pushed(SendStatusNotification::class)->values();

        $this->assertCount(2, $jobs);
        $this->assertSame(['ops@example.com'], $jobs[0]->to);
        $this->assertContains('fan@example.com', $jobs[1]->to);
    }
}
