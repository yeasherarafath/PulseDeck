<?php

namespace Tests\Feature\Status;

use App\Enums\Status\CheckErrorType;
use App\Enums\Status\CheckResultStatus;
use App\Enums\Status\ServiceStatus;
use App\Models\Status\StatusDailyStat;
use App\Models\Status\StatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceDayIssuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bars_link_to_days_with_failures(): void
    {
        $service = StatusService::factory()->create(['is_public' => true]);
        $date = today()->subDay()->toDateString();

        $this->seedDay($service, $date, total: 5, successful: 3);

        $response = $this->get(route('status.services.show', $service));

        $response->assertOk();
        $response->assertSee(route('status.services.show', ['service' => $service->slug, 'date' => $date]), false);
    }

    public function test_home_bars_link_to_days_with_failures(): void
    {
        $service = StatusService::factory()->create(['is_public' => true]);
        $date = today()->subDay()->toDateString();

        $this->seedDay($service, $date, total: 5, successful: 3);

        $response = $this->get(route('status.index'));

        $response->assertOk();
        $response->assertSee(route('status.services.show', ['service' => $service->slug, 'date' => $date]), false);
    }

    public function test_status_badge_is_two_segment_with_pulsing_dot(): void
    {
        $service = StatusService::factory()->create(['is_public' => true]);

        $service->checks()->create([
            'success' => true,
            'status' => CheckResultStatus::Operational,
            'http_status' => 200,
            'response_time' => 120,
            'checked_at' => now(),
        ]);
        $service->forceFill(['current_status' => ServiceStatus::Operational])->save();

        $response = $this->get('/status/badge.svg');

        $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $response->assertSee('clipPath', false);
        $response->assertSee('operational', false);
        $response->assertSee('<animate', false);
        $response->assertSee('<title>', false);
    }

    public function test_day_detail_lists_failed_checks_with_errors(): void
    {
        $service = StatusService::factory()->create(['is_public' => true]);
        $date = today()->subDay()->toDateString();

        $this->seedDay($service, $date, total: 5, successful: 3);

        $service->checks()->create([
            'success' => false,
            'status' => CheckResultStatus::Failed,
            'http_status' => 500,
            'response_time' => 812,
            'error_type' => CheckErrorType::Http5xx,
            'error_message' => 'Unexpected HTTP 500 from probe',
            'assertion_result' => ['failures' => [['assertion' => 'status_code', 'message' => 'expected 200, got 500']]],
            'checked_at' => $date.' 12:00:00',
        ]);
        $service->checks()->create([
            'success' => false,
            'status' => CheckResultStatus::Failed,
            'http_status' => 503,
            'response_time' => 901,
            'error_type' => CheckErrorType::Http5xx,
            'error_message' => 'Unexpected HTTP 503 from probe',
            'checked_at' => $date.' 13:00:00',
        ]);

        $response = $this->get(route('status.services.show', ['service' => $service->slug, 'date' => $date]));

        $response->assertOk();
        $response->assertSee('Issues on '.$date);
        $response->assertSee('2 failed of 5 checks');
        $response->assertSee('Unexpected HTTP 500 from probe');
        $response->assertSee('expected 200, got 500');
    }

    public function test_day_detail_with_no_failures_shows_empty_state(): void
    {
        $service = StatusService::factory()->create(['is_public' => true]);
        $date = today()->subDay()->toDateString();

        $this->seedDay($service, $date, total: 4, successful: 4);

        $response = $this->get(route('status.services.show', ['service' => $service->slug, 'date' => $date]));

        $response->assertOk();
        $response->assertSee('No failed checks recorded for this date.');
    }

    public function test_day_detail_with_expired_records_explains_missing_details(): void
    {
        $service = StatusService::factory()->create(['is_public' => true]);
        $date = today()->subDay()->toDateString();

        // Aggregate says 2 failed, but raw checks were pruned by retention.
        $this->seedDay($service, $date, total: 5, successful: 3);

        $response = $this->get(route('status.services.show', ['service' => $service->slug, 'date' => $date]));

        $response->assertOk();
        $response->assertSee('Detailed check records for this date have expired');
    }

    public function test_day_detail_rejects_invalid_dates(): void
    {
        $service = StatusService::factory()->create(['is_public' => true]);

        foreach (['not-a-date', '2026-13-45', '2999-01-01', '2000-01-01'] as $bad) {
            $this->get(route('status.services.show', ['service' => $service->slug, 'date' => $bad]))->assertNotFound();
        }
    }

    public function test_day_detail_on_private_service_is_not_found(): void
    {
        $service = StatusService::factory()->create(['is_public' => false]);

        $this->get(route('status.services.show', ['service' => $service->slug, 'date' => today()->subDay()->toDateString()]))->assertNotFound();
    }

    private function seedDay(StatusService $service, string $date, int $total, int $successful): void
    {
        StatusDailyStat::create([
            'service_id' => $service->id,
            'date' => $date,
            'total_checks' => $total,
            'successful_checks' => $successful,
            'failed_checks' => $total - $successful,
            'uptime_percentage' => $total > 0 ? round($successful / $total * 100, 2) : 100,
        ]);
    }
}
