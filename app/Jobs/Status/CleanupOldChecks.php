<?php

namespace App\Jobs\Status;

use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusCheck;
use App\Models\Status\StatusDailyStat;
use App\Models\Status\StatusNotificationDelivery;
use App\Models\Status\StatusSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Retention enforcement: raw checks are temporary, daily stats live
 * longer, incidents/maintenance are kept (final-plan §6).
 */
class CleanupOldChecks implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        $rawDays = max(1, (int) StatusSetting::get('raw_checks_retention_days', 60));
        $statsDays = max(30, (int) StatusSetting::get('daily_stats_retention_days', 540));
        $auditDays = max(30, (int) StatusSetting::get('audit_retention_days', 365));

        StatusCheck::where('checked_at', '<', now()->subDays($rawDays))->delete();
        StatusDailyStat::where('date', '<', now()->subDays($statsDays)->toDateString())->delete();
        StatusAuditLog::where('created_at', '<', now()->subDays($auditDays))->delete();
        StatusNotificationDelivery::where('created_at', '<', now()->subDays($auditDays))->delete();
    }
}
