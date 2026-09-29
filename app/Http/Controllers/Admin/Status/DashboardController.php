<?php

namespace App\Http\Controllers\Admin\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\StatusCheck;
use App\Models\Status\StatusIncident;
use App\Models\Status\StatusService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $active = StatusService::active();

        $counts = [
            'total' => (clone $active)->count(),
            'operational' => (clone $active)->where('current_status', 'operational')->count(),
            'degraded' => (clone $active)->where('current_status', 'degraded')->count(),
            'outage' => (clone $active)->whereIn('current_status', ['partial_outage', 'major_outage'])->count(),
            'maintenance' => (clone $active)->where('current_status', 'maintenance')->count(),
        ];

        return view('admin.status.dashboard', [
            'counts' => $counts,
            'problems' => StatusService::active()
                ->whereNotIn('current_status', ['operational', 'unknown'])
                ->orderBy('sort_order')
                ->limit(10)
                ->get(),
            'incidents' => StatusIncident::active()->with('service')->latest('started_at')->limit(5)->get(),
            'recentChecks' => StatusCheck::with('service')->orderByDesc('checked_at')->orderByDesc('id')->limit(10)->get(),
            'avgResponse' => StatusCheck::where('checked_at', '>=', now()->subDay())->whereNotNull('response_time')->avg('response_time'),
            'heartbeat' => cache('status:monitor:heartbeat'),
        ]);
    }
}
