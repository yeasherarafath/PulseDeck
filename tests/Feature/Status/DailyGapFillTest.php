<?php

namespace Tests\Feature\Status;

use App\Models\Status\StatusDailyStat;
use App\Models\Status\StatusService;
use App\Services\Status\PublicStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyGapFillTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_payload_fills_missing_days(): void
    {
        $service = StatusService::factory()->create(['is_public' => true, 'is_active' => true]);
        $svc = new PublicStatusService;
        $payload = $svc->servicePayload($service);

        $this->assertCount(90, $payload['daily']);
        $this->assertTrue(collect($payload['daily'])->every(fn ($d) => $d['uptime'] === null));

        StatusDailyStat::create([
            'service_id' => $service->id,
            'date' => today()->toDateString(),
            'total_checks' => 10,
            'successful_checks' => 10,
            'failed_checks' => 0,
            'uptime_percentage' => 100,
        ]);

        $svc->forgetService($service->id);
        $payload2 = $svc->servicePayload($service->fresh());

        $this->assertCount(90, $payload2['daily']);
        $this->assertEquals(1, collect($payload2['daily'])->whereNotNull('uptime')->count());
        $this->assertEquals(89, collect($payload2['daily'])->whereNull('uptime')->count());
    }

    public function test_calculate_daily_backfills_trailing_days(): void
    {
        StatusService::factory()->create(['is_active' => true]);
        $this->artisan('status:calculate-daily --days=5')->assertSuccessful();
        $this->artisan('status:calculate-daily --date='.today()->subDay()->toDateString())->assertSuccessful();
    }
}
