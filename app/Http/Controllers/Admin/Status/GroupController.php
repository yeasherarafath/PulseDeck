<?php

namespace App\Http\Controllers\Admin\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusServiceGroup;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GroupController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('status.services.view');

        return view('admin.status.groups.index', [
            'groups' => StatusServiceGroup::withCount('services')->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('status.services.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255', 'unique:status_service_groups,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $group = StatusServiceGroup::create([
            'name' => $validated['name'],
            'slug' => ($validated['slug'] ?? null) ?: Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        StatusAuditLog::record('group.created', $group, null, ['name' => $group->name]);

        return redirect()->route('admin.status.groups.index')->with('status', "Group [{$group->name}] created.");
    }

    public function update(Request $request, StatusServiceGroup $group): RedirectResponse
    {
        $this->authorize('status.services.update');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255', 'unique:status_service_groups,slug,'.$group->id],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $group->forceFill([
            'name' => $validated['name'],
            'slug' => ($validated['slug'] ?? null) ?: $group->slug,
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ])->save();

        StatusAuditLog::record('group.updated', $group->fresh(), null, ['name' => $group->name]);

        return redirect()->route('admin.status.groups.index')->with('status', "Group [{$group->name}] updated.");
    }

    public function destroy(Request $request, StatusServiceGroup $group): RedirectResponse
    {
        $this->authorize('status.services.delete');

        if ($group->services()->exists()) {
            return redirect()->route('admin.status.groups.index')
                ->with('error', "Group [{$group->name}] still has services. Move them first.");
        }

        StatusAuditLog::record('group.deleted', $group, ['name' => $group->name], null);

        $group->delete();

        return redirect()->route('admin.status.groups.index')->with('status', 'Group deleted.');
    }
}
