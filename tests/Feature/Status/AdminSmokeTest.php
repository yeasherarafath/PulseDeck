<?php

namespace Tests\Feature\Status;

use App\Enums\Status\ServiceStatus;
use App\Jobs\Status\CheckService;
use App\Models\Status\StatusIncident;
use App\Models\Status\StatusMaintenance;
use App\Models\Status\StatusNotificationChannel;
use App\Models\Status\StatusService;
use App\Models\Status\StatusServiceGroup;
use App\Models\Status\StatusSetting;
use App\Models\Status\StatusSubscriber;
use App\Models\User;
use App\Services\Status\AssertionEngine;
use App\Services\Status\HttpChecker;
use App\Services\Status\IncidentManager;
use App\Services\Status\MaintenanceManager;
use App\Services\Status\RequestBuilder;
use App\Services\Status\StatusCalculator;
use Database\Seeders\HeaderPresetSeeder;
use Database\Seeders\HeaderTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StatusSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Every admin/public page renders and every core action works end to end.
 */
class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, StatusSettingSeeder::class, HeaderPresetSeeder::class, HeaderTemplateSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
    }

    private function serviceData(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Smoke Site',
            'url' => 'https://example.com/health',
            'method' => 'GET',
            'check_interval' => 300,
            'timeout' => 15,
            'connect_timeout' => 5,
            'max_redirects' => 5,
            'http_version' => 'auto',
            'auth' => ['type' => 'none'],
            'body_type' => 'none',
            'expected_statuses' => '200',
            'is_active' => '1',
            'is_public' => '1',
        ];
    }

    public function test_api_docs_page_lists_every_public_endpoint(): void
    {
        $this->actingAs($this->admin)->get('/admin/status/api-docs')
            ->assertOk()
            ->assertSee('/api/status/services/{slug}', false)
            ->assertSee('/api/status/incidents', false)
            ->assertSee('curl -s', false)
            ->assertSee('API docs');

        $viewer = User::factory()->create();
        $viewer->assignRole('status-viewer');
        $this->actingAs($viewer)->get('/admin/status/api-docs')->assertOk();
    }

    public function test_guests_are_redirected_and_viewers_cannot_write(): void
    {
        $this->get('/admin/status')->assertRedirect('/login');

        $viewer = User::factory()->create();
        $viewer->assignRole('status-viewer');

        $this->actingAs($viewer)->get('/admin/status')->assertOk();
        $this->actingAs($viewer)->get('/admin/status/services')->assertOk();
        $this->actingAs($viewer)->post('/admin/status/services', $this->serviceData())->assertForbidden();
        $this->actingAs($viewer)->get('/admin/status/settings')->assertForbidden();
    }

    public function test_every_admin_page_renders_with_data(): void
    {
        $group = StatusServiceGroup::create(['name' => 'Websites', 'slug' => 'websites', 'is_active' => true, 'sort_order' => 0]);
        $service = StatusService::factory()->create(['group_id' => $group->id]);
        $incident = StatusIncident::create([
            'service_id' => $service->id, 'title' => 'Down', 'slug' => 'down-1',
            'status' => 'investigating', 'impact' => 'major', 'started_at' => now(),
        ]);
        $maintenance = StatusMaintenance::create([
            'title' => 'Upgrade', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'status' => 'scheduled',
        ]);
        $maintenance->services()->attach($service->id);
        StatusSubscriber::create(['email' => 'a@example.com', 'verified_at' => now(), 'is_active' => true, 'unsubscribe_token' => 't']);

        $pages = [
            '/admin/status', '/admin/status/monitoring',
            '/admin/status/groups',
            '/admin/status/services', '/admin/status/services/create',
            "/admin/status/services/{$service->slug}", "/admin/status/services/{$service->slug}/edit",
            '/admin/status/incidents', '/admin/status/incidents/create',
            "/admin/status/incidents/{$incident->id}", "/admin/status/incidents/{$incident->id}/edit",
            '/admin/status/maintenances', '/admin/status/maintenances/create', "/admin/status/maintenances/{$maintenance->id}/edit",
            '/admin/status/notifications/channels', '/admin/status/notifications/rules',
            '/admin/status/notifications/subscribers', '/admin/status/notifications/deliveries',
            '/admin/status/settings', '/admin/status/audit-logs', '/admin/status/api-docs',
            '/admin/status/services?search=x&active=1', '/admin/status/incidents?status=resolved',
        ];

        foreach ($pages as $page) {
            $this->actingAs($this->admin)->get($page)->assertOk();
        }
    }

    public function test_public_pages_and_api_render(): void
    {
        $service = StatusService::factory()->create();
        $group = StatusServiceGroup::create(['name' => 'Websites', 'slug' => 'websites', 'is_active' => true, 'sort_order' => 0]);
        $service->forceFill(['group_id' => $group->id, 'current_status' => ServiceStatus::Operational])->save();
        Cache::flush();

        $this->get('/')->assertOk()->assertSee($service->name);
        $this->get('/status')->assertOk();
        $this->get("/status/services/{$service->slug}")->assertOk();
        $this->get('/status/refresh')->assertOk()->assertJsonStructure(['status', 'services']);
        $this->get('/status/badge.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->getJson('/api/status')->assertOk()->assertJsonPath('ok', true);
        $this->getJson('/api/status/services')->assertOk();
        $this->getJson("/api/status/services/{$service->slug}")->assertOk();
        $this->getJson('/api/status/incidents')->assertOk();
        $this->get('/status/services/nope')->assertNotFound();
    }

    public function test_group_service_lifecycle(): void
    {
        $this->actingAs($this->admin)->post('/admin/status/groups', ['name' => 'APIs'])->assertRedirect();
        $group = StatusServiceGroup::where('slug', 'apis')->firstOrFail();

        $this->actingAs($this->admin)->post('/admin/status/services', $this->serviceData(['group_id' => $group->id]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $service = StatusService::where('name', 'Smoke Site')->firstOrFail();
        $this->assertSame('smoke-site', $service->slug);

        $this->actingAs($this->admin)->put("/admin/status/services/{$service->slug}", $this->serviceData(['name' => 'Renamed', 'slug' => 'smoke-site']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $service->fresh()->name);

        $this->actingAs($this->admin)->post("/admin/status/services/{$service->slug}/pause")->assertRedirect();
        $this->assertFalse($service->fresh()->is_active);
        $this->actingAs($this->admin)->post("/admin/status/services/{$service->slug}/pause")->assertRedirect();
        $this->assertTrue($service->fresh()->is_active);

        $this->actingAs($this->admin)->delete("/admin/status/groups/{$group->id}")->assertRedirect();

        $this->actingAs($this->admin)->delete("/admin/status/services/{$service->slug}")->assertRedirect();
        $this->assertNull(StatusService::find($service->id));
    }

    public function test_service_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->admin)->post('/admin/status/services', $this->serviceData(['url' => 'http://127.0.0.1/']))
            ->assertSessionHasErrors('url');
        $this->actingAs($this->admin)->post('/admin/status/services', $this->serviceData(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_check_now_dispatches_and_job_records_result(): void
    {
        $service = StatusService::factory()->create(['url' => 'https://example.com/health']);

        Queue::fake();
        $this->actingAs($this->admin)->post("/admin/status/services/{$service->slug}/check")->assertRedirect();
        Queue::assertPushed(CheckService::class);

        Http::fake(['example.com/*' => Http::response('ok', 200)]);
        (new CheckService($service->id, true))->handle(
            app(RequestBuilder::class),
            app(HttpChecker::class),
            app(AssertionEngine::class),
            app(StatusCalculator::class),
            app(IncidentManager::class),
            app(MaintenanceManager::class),
        );

        $this->assertSame(ServiceStatus::Operational, $service->fresh()->current_status);
        $this->assertSame(1, $service->checks()->count());
    }

    public function test_test_request_endpoint_returns_result(): void
    {
        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $this->actingAs($this->admin)->postJson('/admin/status/services/test', $this->serviceData())
            ->assertOk();
    }

    public function test_incident_lifecycle_and_public_visibility(): void
    {
        $service = StatusService::factory()->create();

        $this->actingAs($this->admin)->post('/admin/status/incidents', [
            'service_id' => $service->id, 'title' => 'API slow', 'impact' => 'minor', 'message' => 'Looking into it',
        ])->assertRedirect();

        $incident = StatusIncident::where('title', 'API slow')->firstOrFail();
        $this->get("/status/incidents/{$incident->slug}")->assertOk()->assertSee('API slow');

        $this->actingAs($this->admin)->post("/admin/status/incidents/{$incident->id}/updates", [
            'status' => 'resolved', 'message' => 'Fixed',
        ])->assertRedirect();

        $this->assertSame('resolved', $incident->fresh()->status->value);
        $this->assertNotNull($incident->fresh()->resolved_at);

        $this->actingAs($this->admin)->delete("/admin/status/incidents/{$incident->id}")->assertRedirect();
        $this->assertNull(StatusIncident::find($incident->id));
    }

    public function test_maintenance_lifecycle(): void
    {
        $service = StatusService::factory()->create();

        $this->actingAs($this->admin)->post('/admin/status/maintenances', [
            'title' => 'DB upgrade',
            'starts_at' => now()->addDay()->format('Y-m-d H:i'),
            'ends_at' => now()->addDays(2)->format('Y-m-d H:i'),
            'services' => [$service->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $maintenance = StatusMaintenance::where('title', 'DB upgrade')->firstOrFail();
        $this->assertCount(1, $maintenance->services);

        $this->actingAs($this->admin)->post('/admin/status/maintenances', [
            'title' => 'Bad', 'starts_at' => now()->addDays(2)->format('Y-m-d H:i'),
            'ends_at' => now()->addDay()->format('Y-m-d H:i'), 'services' => [$service->id],
        ])->assertSessionHasErrors('ends_at');

        $service->forceFill(['next_check_at' => now()->addHour()])->save();

        $this->actingAs($this->admin)->post("/admin/status/maintenances/{$maintenance->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $maintenance->fresh()->status->value);
        $this->assertTrue($service->fresh()->next_check_at->lte(now()), 'affected services are re-checked right away');

        $this->actingAs($this->admin)->delete("/admin/status/maintenances/{$maintenance->id}")->assertRedirect();
        $this->assertNull(StatusMaintenance::find($maintenance->id));
    }

    public function test_public_page_reflects_admin_changes_immediately(): void
    {
        $service = StatusService::factory()->create(['name' => 'Before Name', 'current_status' => ServiceStatus::Operational]);

        $this->get('/')->assertSee('Before Name');

        $this->actingAs($this->admin)->put("/admin/status/services/{$service->slug}", $this->serviceData(['name' => 'After Name', 'slug' => $service->slug]))
            ->assertSessionHasNoErrors();
        $this->get('/')->assertSee('After Name')->assertDontSee('Before Name');

        $this->actingAs($this->admin)->post('/admin/status/incidents', [
            'service_id' => $service->id, 'title' => 'Visible now', 'impact' => 'minor', 'message' => 'x',
        ]);
        $incident = StatusIncident::where('title', 'Visible now')->firstOrFail();
        $this->get('/')->assertSee('Visible now');

        $this->actingAs($this->admin)->post("/admin/status/incidents/{$incident->id}/updates", ['status' => 'resolved', 'message' => 'done']);
        $this->get('/')->assertDontSee('Visible now');

        $this->actingAs($this->admin)->post("/admin/status/services/{$service->slug}/pause");
        $this->actingAs($this->admin)->followingRedirects()->delete("/admin/status/services/{$service->slug}");
        $this->get('/')->assertDontSee('After Name');
    }

    public function test_maintenance_times_are_entered_in_the_display_timezone(): void
    {
        StatusSetting::set('timezone', 'Asia/Dhaka');
        Cache::flush();
        $service = StatusService::factory()->create();

        $this->actingAs($this->admin)->post('/admin/status/maintenances', [
            'title' => 'TZ window',
            'starts_at' => '2030-01-01T14:00',
            'ends_at' => '2030-01-01T16:00',
            'services' => [$service->id],
        ])->assertSessionHasNoErrors();

        $maintenance = StatusMaintenance::where('title', 'TZ window')->firstOrFail();
        $this->assertSame('2030-01-01 08:00', $maintenance->starts_at->utc()->format('Y-m-d H:i'));

        $this->actingAs($this->admin)->get("/admin/status/maintenances/{$maintenance->id}/edit")
            ->assertOk()->assertSee('2030-01-01T14:00', false);
    }

    public function test_notification_channels_rules_and_subscribers(): void
    {
        $this->actingAs($this->admin)->post('/admin/status/notifications/channels', [
            'name' => 'Ops', 'type' => 'mail', 'recipients' => 'ops@example.com', 'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $channel = StatusNotificationChannel::where('name', 'Ops')->firstOrFail();

        $this->actingAs($this->admin)->post('/admin/status/notifications/rules', [
            'channel_id' => $channel->id, 'event' => 'service.failed', 'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, $channel->rules()->count());

        $subscriber = StatusSubscriber::create(['email' => 'x@example.com', 'verified_at' => now(), 'is_active' => true, 'unsubscribe_token' => 'tt']);

        $this->actingAs($this->admin)->post("/admin/status/notifications/subscribers/{$subscriber->id}/toggle")->assertRedirect();
        $this->assertFalse($subscriber->fresh()->is_active);

        $this->actingAs($this->admin)->delete("/admin/status/notifications/subscribers/{$subscriber->id}")->assertRedirect();
        $this->assertNull(StatusSubscriber::find($subscriber->id));

        $this->actingAs($this->admin)->delete("/admin/status/notifications/channels/{$channel->id}")->assertRedirect();
        $this->assertNull(StatusNotificationChannel::find($channel->id));
    }

    public function test_login_logout_flow(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass-1')]);
        $user->assignRole('super-admin');

        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-1'])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
        $this->get('/forgot-password')->assertOk();
    }

    public function test_status_commands_run(): void
    {
        StatusService::factory()->create(['next_check_at' => now()->subMinute()]);
        Queue::fake();

        $this->artisan('status:dispatch-due')->assertSuccessful();
        Queue::assertPushed(CheckService::class);

        $this->artisan('status:calculate-daily')->assertSuccessful();
        $this->artisan('status:cleanup')->assertSuccessful();
        $this->artisan('status:recalculate')->assertSuccessful();
    }
}
