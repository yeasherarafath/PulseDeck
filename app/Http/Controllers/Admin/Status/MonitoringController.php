<?php

namespace App\Http\Controllers\Admin\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\StatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(): View
    {
        $this->authorize('status.monitoring.view');

        $services = StatusService::with(['group', 'checks' => fn ($query) => $query->orderByDesc('checked_at')->orderByDesc('id')->limit(1)])
            ->orderBy('next_check_at')
            ->get();

        return view('admin.status.monitoring.index', [
            'services' => $services,
            'queuedJobs' => DB::table('jobs')->count(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'heartbeat' => cache('status:monitor:heartbeat'),
        ]);
    }
}
