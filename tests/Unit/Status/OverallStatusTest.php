<?php

namespace Tests\Unit\Status;

use App\Enums\Status\ServiceStatus;
use App\Services\Status\PublicStatusService;
use PHPUnit\Framework\TestCase;

class OverallStatusTest extends TestCase
{
    private function overall(ServiceStatus ...$statuses): ServiceStatus
    {
        return (new PublicStatusService)->overallStatus($statuses);
    }

    public function test_worst_status_wins(): void
    {
        $this->assertSame(ServiceStatus::MajorOutage, $this->overall(ServiceStatus::Operational, ServiceStatus::MajorOutage));
        $this->assertSame(ServiceStatus::PartialOutage, $this->overall(ServiceStatus::Degraded, ServiceStatus::PartialOutage));
        $this->assertSame(ServiceStatus::Degraded, $this->overall(ServiceStatus::Operational, ServiceStatus::Degraded));
    }

    public function test_all_operational_and_empty_are_operational(): void
    {
        $this->assertSame(ServiceStatus::Operational, $this->overall(ServiceStatus::Operational));
        $this->assertSame(ServiceStatus::Operational, $this->overall());
    }

    public function test_all_maintenance_and_all_unknown(): void
    {
        $this->assertSame(ServiceStatus::Maintenance, $this->overall(ServiceStatus::Maintenance, ServiceStatus::Maintenance));
        $this->assertSame(ServiceStatus::Unknown, $this->overall(ServiceStatus::Unknown, ServiceStatus::Unknown));
        $this->assertSame(ServiceStatus::Operational, $this->overall(ServiceStatus::Unknown, ServiceStatus::Operational));
    }
}
