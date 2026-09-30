<?php

namespace App\Services\Status;

use App\Enums\Status\IntervalUnit;
use App\Enums\Status\ScheduleType;
use App\Models\Status\StatusService;
use Carbon\Carbon;
use Cron\CronExpression;
use Throwable;

/**
 * Single owner of "when does this service run next?".
 *
 * Two schedule modes:
 * - interval: every N seconds (from value + unit, persisted as check_interval).
 * - cron: standard 5-part cron expression, evaluated in the app timezone.
 *   The every-minute `status:dispatch-due` dispatcher is the tick, so
 *   sub-minute precision is not possible in either mode.
 */
class CheckScheduler
{
    public const MIN_SECONDS = 60;

    public const MAX_SECONDS = 31536000; // 1 year (months ≈ 30d, years ≈ 365d).

    public static function isValidCron(?string $expression): bool
    {
        if (! is_string($expression) || trim($expression) === '') {
            return false;
        }

        try {
            return CronExpression::isValidExpression(trim($expression));
        } catch (Throwable) {
            return false;
        }
    }

    public static function toSeconds(int $value, IntervalUnit $unit): int
    {
        return max(self::MIN_SECONDS, min(self::MAX_SECONDS, $value * $unit->seconds()));
    }

    /**
     * Pick the largest unit that divides the seconds evenly for tidy prefills
     * (300 → [5, minutes], 3600 → [1, hours]); falls back to seconds.
     *
     * @return array{value: int, unit: IntervalUnit}
     */
    public static function fromSeconds(int $seconds): array
    {
        $seconds = max(self::MIN_SECONDS, min(self::MAX_SECONDS, $seconds));

        foreach ([IntervalUnit::Years, IntervalUnit::Months, IntervalUnit::Weeks, IntervalUnit::Days, IntervalUnit::Hours, IntervalUnit::Minutes] as $unit) {
            if ($seconds % $unit->seconds() === 0) {
                return ['value' => (int) ($seconds / $unit->seconds()), 'unit' => $unit];
            }
        }

        return ['value' => $seconds, 'unit' => IntervalUnit::Seconds];
    }

    public static function humanize(int $seconds): string
    {
        ['value' => $value, 'unit' => $unit] = self::fromSeconds($seconds);

        $noun = match ($unit) {
            IntervalUnit::Seconds => $value === 1 ? 'second' : 'seconds',
            IntervalUnit::Minutes => $value === 1 ? 'minute' : 'minutes',
            IntervalUnit::Hours => $value === 1 ? 'hour' : 'hours',
            IntervalUnit::Days => $value === 1 ? 'day' : 'days',
            IntervalUnit::Weeks => $value === 1 ? 'week' : 'weeks',
            IntervalUnit::Months => $value === 1 ? 'month' : 'months',
            IntervalUnit::Years => $value === 1 ? 'year' : 'years',
        };

        return "{$value} {$noun}";
    }

    /**
     * Next run after $from (defaults to now). Cron failures fall back to
     * interval math so a bad stored expression never bricks dispatching.
     */
    public function nextRunAt(StatusService $service, ?Carbon $from = null): Carbon
    {
        $from = ($from?->copy()) ?? now();

        if (($service->schedule_type?->value ?? ScheduleType::Interval->value) === ScheduleType::Cron->value) {
            $cronNext = self::nextRunFromCron((string) ($service->cron_expression ?? ''), $from);

            if ($cronNext !== null) {
                // Never schedule in the past: the dispatcher ticks every
                // minute, so push sub-minute cron hits to the next tick.
                return $cronNext->lte($from) ? $from->copy()->addMinute()->startOfMinute() : $cronNext;
            }
        }

        return $from->copy()->addSeconds(max(self::MIN_SECONDS, (int) $service->check_interval));
    }

    public static function nextRunFromCron(string $expression, ?Carbon $from = null): ?Carbon
    {
        $expression = trim($expression);

        if (! self::isValidCron($expression)) {
            return null;
        }

        try {
            $run = (new CronExpression($expression))->getNextRunDate($from ?? now());

            return Carbon::instance($run);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<Carbon>
     */
    public static function upcomingRunsFromCron(string $expression, int $count = 3): array
    {
        $expression = trim($expression);

        if (! self::isValidCron($expression) || $count < 1) {
            return [];
        }

        try {
            $dates = (new CronExpression($expression))->getMultipleRunDates(min($count, 5));

            return array_map(fn ($date) => Carbon::instance($date), is_array($dates) ? $dates : iterator_to_array($dates));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Estimated gap between cron runs (median of the next few gaps),
     * used for stale detection and checks/day estimates.
     */
    public static function estimatedCronInterval(string $expression): ?int
    {
        $runs = self::upcomingRunsFromCron($expression, 4);

        if (count($runs) < 2) {
            return null;
        }

        $gaps = [];

        for ($i = 1; $i < count($runs); $i++) {
            $gaps[] = $runs[$i]->diffInSeconds($runs[$i - 1]);
        }

        sort($gaps);

        return (int) $gaps[(int) floor(count($gaps) / 2)];
    }

    /**
     * Grace window before a service is flagged Unknown (no successful
     * dispatch for a while — likely worker/scheduler outage).
     */
    public function staleGraceSeconds(StatusService $service, int $multiplier): int
    {
        $multiplier = max(1, $multiplier);

        if (($service->schedule_type?->value ?? 'interval') === ScheduleType::Cron->value) {
            $gap = self::estimatedCronInterval((string) ($service->cron_expression ?? '')) ?? 3600;

            return max(900, $gap) * $multiplier;
        }

        return max(self::MIN_SECONDS, (int) $service->check_interval) * $multiplier;
    }

    public function describe(StatusService $service): string
    {
        if (($service->schedule_type?->value ?? 'interval') === ScheduleType::Cron->value && trim((string) ($service->cron_expression ?? '')) !== '') {
            return 'Cron `'.trim((string) $service->cron_expression).'`';
        }

        return 'Every '.self::humanize((int) $service->check_interval);
    }

    /**
     * @return array<string, string> expression => label for the cron preset dropdown.
     */
    public static function cronPresets(): array
    {
        return [
            '* * * * *' => 'Every minute',
            '*/5 * * * *' => 'Every 5 minutes',
            '*/15 * * * *' => 'Every 15 minutes',
            '*/30 * * * *' => 'Every 30 minutes',
            '0 * * * *' => 'Hourly (top of the hour)',
            '0 */6 * * *' => 'Every 6 hours',
            '0 0 * * *' => 'Daily at midnight',
            '0 9 * * *' => 'Daily at 09:00',
            '0 9 * * 1-5' => 'Weekdays at 09:00',
            '0 0 * * 0' => 'Weekly (Sunday midnight)',
        ];
    }

    /**
     * @return list<array{seconds: int, label: string}>
     */
    public static function intervalPresets(): array
    {
        return [
            ['seconds' => 60, 'label' => '1 min'],
            ['seconds' => 300, 'label' => '5 min'],
            ['seconds' => 600, 'label' => '10 min'],
            ['seconds' => 900, 'label' => '15 min'],
            ['seconds' => 1800, 'label' => '30 min'],
            ['seconds' => 3600, 'label' => '1 hour'],
            ['seconds' => 21600, 'label' => '6 hours'],
            ['seconds' => 43200, 'label' => '12 hours'],
            ['seconds' => 86400, 'label' => '24 hours'],
            ['seconds' => 604800, 'label' => '1 week'],
            ['seconds' => 2592000, 'label' => '1 month'],
        ];
    }
}
