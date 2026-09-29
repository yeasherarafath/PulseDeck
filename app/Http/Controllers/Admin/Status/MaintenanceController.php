<?php

namespace App\Http\Controllers\Admin\Status;

use App\Enums\Status\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusMaintenance;
use App\Models\Status\StatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('status.maintenance.view');

        return view('admin.status.maintenances.index', [
            'maintenances' => StatusMaintenance::with('services')->orderBy('starts_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('status.maintenance.create');

        return view('admin.status.maintenances.create', [
            'services' => StatusService::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('status.maintenance.create');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['exists:status_services,id'],
            'notify' => ['sometimes', 'boolean'],
        ]);

        $maintenance = StatusMaintenance::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'status' => MaintenanceStatus::Scheduled,
            'created_by' => $request->user()->id,
        ]);

        $maintenance->services()->sync($validated['services']);

        StatusAuditLog::record('maintenance.created', $maintenance, null, ['title' => $maintenance->title]);

        return redirect()->route('admin.status.maintenances.index')
            ->with('status', "Maintenance [{$maintenance->title}] scheduled.");
    }

    public function edit(StatusMaintenance $maintenance): View
    {
        $this->authorize('status.maintenance.update');

        return view('admin.status.maintenances.edit', [
            'maintenance' => $maintenance->load('services'),
            'services' => StatusService::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, StatusMaintenance $maintenance): RedirectResponse
    {
        $this->authorize('status.maintenance.update');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['exists:status_services,id'],
        ]);

        $maintenance->forceFill([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
        ])->save();

        $maintenance->services()->sync($validated['services']);

        StatusAuditLog::record('maintenance.updated', $maintenance, null, ['title' => $maintenance->title]);

        return redirect()->route('admin.status.maintenances.index')
            ->with('status', "Maintenance [{$maintenance->title}] updated.");
    }

    public function destroy(Request $request, StatusMaintenance $maintenance): RedirectResponse
    {
        $this->authorize('status.maintenance.delete');

        StatusAuditLog::record('maintenance.deleted', $maintenance, ['title' => $maintenance->title], null);

        $maintenance->delete();

        return redirect()->route('admin.status.maintenances.index')
            ->with('status', 'Maintenance deleted.');
    }

    public function cancel(Request $request, StatusMaintenance $maintenance): RedirectResponse
    {
        $this->authorize('status.maintenance.update');

        $maintenance->forceFill(['status' => MaintenanceStatus::Cancelled])->save();

        StatusAuditLog::record('maintenance.cancelled', $maintenance, null, ['status' => 'cancelled']);

        return redirect()->route('admin.status.maintenances.index')
            ->with('status', "Maintenance [{$maintenance->title}] cancelled.");
    }
}
