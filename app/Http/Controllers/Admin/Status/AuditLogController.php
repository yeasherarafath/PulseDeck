<?php

namespace App\Http\Controllers\Admin\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('status.settings.manage');

        $logs = StatusAuditLog::with('user')
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', '%'.$request->input('action').'%'))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.status.audit.index', [
            'logs' => $logs,
            'filter' => $request->input('action', ''),
        ]);
    }
}
