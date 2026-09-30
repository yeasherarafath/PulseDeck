<?php

namespace App\Http\Controllers\Admin\Status;

use App\Enums\Status\IncidentStatus;
use App\Events\Status\IncidentCreated;
use App\Events\Status\IncidentResolved;
use App\Events\Status\IncidentUpdated;
use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusIncident;
use App\Models\Status\StatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IncidentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('status.incidents.view');

        $incidents = StatusIncident::with('service')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('started_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.status.incidents.index', [
            'incidents' => $incidents,
            'filter' => $request->input('status'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('status.incidents.create');

        $preselectedServiceId = $request->integer('service') ?: null;

        if ($preselectedServiceId !== null && ! StatusService::whereKey($preselectedServiceId)->exists()) {
            $preselectedServiceId = null;
        }

        return view('admin.status.incidents.create', [
            'services' => StatusService::orderBy('name')->get(),
            'preselectedServiceId' => $preselectedServiceId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('status.incidents.create');

        $validated = $request->validate([
            'service_id' => ['nullable', 'exists:status_services,id'],
            'title' => ['required', 'string', 'max:255'],
            'impact' => ['required', 'in:minor,major,critical'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $incident = StatusIncident::create([
            'service_id' => $validated['service_id'] ?? null,
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title'].'-'.now()->format('Ymd-Hi-s')),
            'status' => IncidentStatus::Investigating,
            'impact' => $validated['impact'],
            'started_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        $incident->updates()->create([
            'status' => IncidentStatus::Investigating,
            'message' => $validated['message'],
            'created_by' => $request->user()->id,
        ]);

        StatusAuditLog::record('incident.created', $incident, null, ['title' => $incident->title]);

        IncidentCreated::dispatch($incident);

        return redirect()->route('admin.status.incidents.show', $incident)
            ->with('status', "Incident [{$incident->title}] created.");
    }

    public function show(StatusIncident $incident): View
    {
        $this->authorize('status.incidents.view');

        $incident->load(['service', 'updates']);

        return view('admin.status.incidents.show', compact('incident'));
    }

    public function edit(StatusIncident $incident): View
    {
        $this->authorize('status.incidents.update');

        return view('admin.status.incidents.edit', [
            'incident' => $incident,
            'services' => StatusService::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, StatusIncident $incident): RedirectResponse
    {
        $this->authorize('status.incidents.update');

        $validated = $request->validate([
            'service_id' => ['nullable', 'exists:status_services,id'],
            'title' => ['required', 'string', 'max:255'],
            'impact' => ['required', 'in:minor,major,critical'],
            'status' => ['required', 'in:investigating,identified,monitoring,resolved'],
        ]);

        $before = $incident->status;
        $newStatus = IncidentStatus::from($validated['status']);

        $incident->forceFill([
            'service_id' => $validated['service_id'] ?? null,
            'title' => $validated['title'],
            'impact' => $validated['impact'],
            'status' => $newStatus,
            'resolved_at' => $newStatus === IncidentStatus::Resolved ? ($incident->resolved_at ?? now()) : null,
            'updated_by' => $request->user()->id,
        ])->save();

        StatusAuditLog::record('incident.updated', $incident, ['status' => $before->value], ['status' => $newStatus->value]);

        if ($before !== $newStatus && $newStatus === IncidentStatus::Resolved) {
            IncidentResolved::dispatch($incident->fresh());
        } else {
            IncidentUpdated::dispatch($incident->fresh());
        }

        return redirect()->route('admin.status.incidents.show', $incident)
            ->with('status', "Incident [{$incident->title}] updated.");
    }

    public function destroy(Request $request, StatusIncident $incident): RedirectResponse
    {
        $this->authorize('status.incidents.delete');

        StatusAuditLog::record('incident.deleted', $incident, ['title' => $incident->title], null);

        $incident->delete();

        return redirect()->route('admin.status.incidents.index')
            ->with('status', 'Incident deleted.');
    }

    public function storeUpdate(Request $request, StatusIncident $incident): RedirectResponse
    {
        $this->authorize('status.incidents.update');

        $validated = $request->validate([
            'status' => ['required', 'in:investigating,identified,monitoring,resolved'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $newStatus = IncidentStatus::from($validated['status']);

        $incident->updates()->create([
            'status' => $newStatus,
            'message' => $validated['message'],
            'created_by' => $request->user()->id,
        ]);

        $wasResolved = $incident->status === IncidentStatus::Resolved;

        $incident->forceFill([
            'status' => $newStatus,
            'resolved_at' => $newStatus === IncidentStatus::Resolved ? ($incident->resolved_at ?? now()) : null,
            'updated_by' => $request->user()->id,
        ])->save();

        StatusAuditLog::record('incident.updated', $incident, null, ['update' => mb_substr($validated['message'], 0, 200)]);

        if ($newStatus === IncidentStatus::Resolved && ! $wasResolved) {
            IncidentResolved::dispatch($incident->fresh());
        } else {
            IncidentUpdated::dispatch($incident->fresh());
        }

        return redirect()->route('admin.status.incidents.show', $incident)
            ->with('status', 'Update posted.');
    }
}
