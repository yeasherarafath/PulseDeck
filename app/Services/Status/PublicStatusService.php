<?php

namespace App\Services\Status;

use App\Enums\Status\MaintenanceStatus;
use App\Enums\Status\ServiceStatus;
use App\Models\Status\StatusIncident;
use App\Models\Status\StatusMaintenance;
use App\Models\Status\StatusService;
use App\Models\Status\StatusServiceGroup;
use App\Models\Status\StatusSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Cached public status payload. All public pages and the polling
 * endpoint read through here — no per-visitor database storms.
 */
class PublicStatusService
{
    public const CACHE_KEY = 'status:public-v2';

    public const CACHE_TTL = 30;

    /**
     * @return array<string, mixed> Plain scalars/arrays only — safe to cache across processes.
     */
    public function payload(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => $this->buildPayload());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Drop cached public data after anything visitors can see has changed.
     * Pass a service id to also drop just that service page; omit it to drop
     * every per-service payload (group renames, settings, maintenance).
     */
    public static function flush(?int $serviceId = null): void
    {
        Cache::forget(self::CACHE_KEY);

        if ($serviceId !== null) {
            Cache::forget(self::serviceKey($serviceId));

            return;
        }

        StatusService::query()->pluck('id')->each(fn (int $id) => Cache::forget(self::serviceKey($id)));
    }

    public static function serviceKey(int $serviceId): string
    {
        return "status:service-v2:{$serviceId}";
    }

    public function forgetService(int $serviceId): void
    {
        Cache::forget(self::serviceKey($serviceId));
    }

    /**
     * @return array<string, mixed> Plain scalars/arrays only.
     */
    public function servicePayload(StatusService $service): array
    {
        return Cache::remember(self::serviceKey($service->id), self::CACHE_TTL, function () use ($service): array {
            $window = (int) StatusSetting::get('uptime_window_days', 90);

            $stats = $service->dailyStats()
                ->where('date', '>=', now()->subDays($window)->toDateString())
                ->orderBy('date')
                ->get();

            $responseTimes = $service->checks()
                ->whereNotNull('response_time')
                ->orderByDesc('checked_at')
                ->orderByDesc('id')
                ->limit(100)
                ->get(['checked_at', 'response_time'])
                ->reverse()
                ->values();

            $incidents = StatusIncident::where('service_id', $service->id)
                ->latest('started_at')
                ->limit(5)
                ->get();

            return [
                'service' => $this->serviceRow($service),
                'group_name' => $service->group?->name,
                'window_days' => $window,
                'daily' => $stats->map(fn ($stat) => [
                    'date' => $stat->date->toDateString(),
                    'uptime' => (float) $stat->uptime_percentage,
                    'total' => $stat->total_checks,
                    'successful' => $stat->successful_checks,
                ])->all(),
                'uptime_90' => $this->windowUptime($service, $window),
                'response_times' => $responseTimes->map(fn ($check) => [
                    't' => $check->checked_at->toIso8601String(),
                    'ms' => $check->response_time,
                ])->all(),
                'avg_response' => $responseTimes->avg('response_time'),
                'incidents' => $incidents->map(fn ($incident) => $this->incidentRow($incident))->all(),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(): array
    {
        $groups = StatusServiceGroup::active()->ordered()->with(['services' => function ($query): void {
            $query->public()->orderBy('sort_order')->orderBy('name');
        }])->get();

        // Public services without a group must still be visible.
        $ungrouped = StatusService::query()->public()->whereNull('group_id')->orderBy('sort_order')->orderBy('name')->get();

        $services = $groups->pluck('services')->flatten()->concat($ungrouped);

        $statuses = $services->map(fn (StatusService $service) => $service->current_status)->filter();

        $overall = $this->overallStatus($statuses->all());

        // "Last updated" means last time a public service was actually
        // checked — not when this cached payload was built (which would
        // always read "0 seconds ago").
        $latestCheck = $services->pluck('last_checked_at')->filter()->max();

        $window = (int) StatusSetting::get('uptime_window_days', 90);

        $groupRows = [];
        $uptime = [];

        foreach ($groups as $group) {
            if ($group->services->isEmpty()) {
                continue;
            }

            $serviceRows = [];

            foreach ($group->services as $service) {
                $serviceRows[] = $this->serviceRow($service);
                $uptime[$service->id] = $this->dailyBars($service, $window);
            }

            $groupRows[] = ['name' => $group->name, 'services' => $serviceRows];
        }

        if ($ungrouped->isNotEmpty()) {
            $serviceRows = [];

            foreach ($ungrouped as $service) {
                $serviceRows[] = $this->serviceRow($service);
                $uptime[$service->id] = $this->dailyBars($service, $window);
            }

            $groupRows[] = ['name' => $groups->isEmpty() ? 'Services' : 'Other services', 'services' => $serviceRows];
        }

        return [
            'status' => $overall->value,
            'status_label' => $this->overallLabel($overall),
            'updated_at' => $latestCheck?->toIso8601String() ?? now()->toIso8601String(),
            'updated_human' => $latestCheck?->diffForHumans() ?? 'never',
            'server_time' => setting_time(now(), 'M j, Y H:i').' '.setting_timezone(),
            'server_tz' => setting_timezone(),
            'groups' => $groupRows,
            'incidents' => StatusIncident::active()->visibleToPublic()->with('service')->latest('started_at')->limit(10)->get()
                ->map(fn ($incident) => $this->incidentRow($incident))->all(),
            'maintenances' => StatusMaintenance::whereIn('status', [MaintenanceStatus::Scheduled, MaintenanceStatus::Active])
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(10)
                ->get()
                ->map(fn ($maintenance) => [
                    'title' => $maintenance->title,
                    'description' => $maintenance->description,
                    'starts' => setting_time($maintenance->starts_at),
                    'ends' => setting_time($maintenance->ends_at),
                    'status' => $maintenance->status->value,
                ])->all(),
            'uptime' => $uptime,
            'window_days' => $window,
            'refresh_seconds' => (int) StatusSetting::get('public_refresh_seconds', 45),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceRow(StatusService $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'status' => $service->current_status->value,
            'last_checked_human' => $service->last_checked_at?->diffForHumans(),
            'last_checked_iso' => $service->last_checked_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function incidentRow(StatusIncident $incident): array
    {
        return [
            'title' => $incident->title,
            'slug' => $incident->slug,
            'status' => $incident->status->value,
            'impact' => $incident->impact->value,
            'service_name' => $incident->service?->name,
            'started' => setting_time($incident->started_at, 'M j, Y H:i'),
            'started_human' => $incident->started_at->diffForHumans(),
            'resolved' => $incident->resolved_at ? setting_time($incident->resolved_at, 'M j, Y H:i') : null,
            'updates' => $incident->relationLoaded('updates')
                ? $incident->updates->map(fn ($update) => [
                    'status' => $update->status->value,
                    'status_label' => $update->status->label(),
                    'message' => $update->message,
                    'at' => setting_time($update->created_at, 'M j, Y H:i'),
                ])->all()
                : [],
        ];
    }

    /**
     * @param  list<ServiceStatus>  $statuses
     */
    public function overallStatus(array $statuses): ServiceStatus
    {
        $values = array_map(fn (ServiceStatus $status) => $status->value, $statuses);

        if (in_array(ServiceStatus::MajorOutage->value, $values, true)) {
            return ServiceStatus::MajorOutage;
        }

        if (in_array(ServiceStatus::PartialOutage->value, $values, true)) {
            return ServiceStatus::PartialOutage;
        }

        if (in_array(ServiceStatus::Degraded->value, $values, true)) {
            return ServiceStatus::Degraded;
        }

        if ($values !== [] && count(array_unique($values)) === 1 && $values[0] === ServiceStatus::Maintenance->value) {
            return ServiceStatus::Maintenance;
        }

        // Nothing has been checked yet: do not claim "operational".
        if ($values !== [] && count(array_unique($values)) === 1 && $values[0] === ServiceStatus::Unknown->value) {
            return ServiceStatus::Unknown;
        }

        return ServiceStatus::Operational;
    }

    public function overallLabel(ServiceStatus $status): string
    {
        return match ($status) {
            ServiceStatus::Operational => 'All Systems Operational',
            ServiceStatus::Degraded => 'Some systems are experiencing degraded performance',
            ServiceStatus::PartialOutage => 'Some systems are experiencing a partial outage',
            ServiceStatus::MajorOutage => 'Major outage in progress',
            ServiceStatus::Maintenance => 'Scheduled maintenance in progress',
            ServiceStatus::Unknown => 'Status currently unknown',
        };
    }

    /**
     * @return array{uptime: float, total: int, successful: int}
     */
    private function windowUptime(StatusService $service, int $window): array
    {
        $stats = $service->dailyStats()
            ->where('date', '>=', now()->subDays($window)->toDateString())
            ->get();

        $total = $stats->sum('total_checks');
        $successful = $stats->sum('successful_checks');

        return [
            'uptime' => $total > 0 ? round($successful / $total * 100, 2) : 100.0,
            'total' => $total,
            'successful' => $successful,
        ];
    }

    /**
     * @return list<array{date: string, uptime: float|null, total: int}>
     */
    private function dailyBars(StatusService $service, int $window): array
    {
        $stats = $service->dailyStats()
            ->where('date', '>=', now()->subDays($window - 1)->toDateString())
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($stat) => $stat->date->toDateString());

        $bars = [];

        for ($i = $window - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $stat = $stats->get($date);

            $bars[] = [
                'date' => $date,
                'uptime' => $stat ? (float) $stat->uptime_percentage : null,
                'total' => $stat?->total_checks ?? 0,
                'successful' => $stat?->successful_checks ?? 0,
            ];
        }

        return $bars;
    }

    public function barColor(?float $uptime): string
    {
        if ($uptime === null) {
            return 'bg-secondary';
        }

        if ($uptime >= 99.9) {
            return 'bg-green';
        }

        if ($uptime >= 99.0) {
            return 'bg-yellow';
        }

        return 'bg-red';
    }
}
