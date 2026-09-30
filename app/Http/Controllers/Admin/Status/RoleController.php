<?php

namespace App\Http\Controllers\Admin\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Built-in roles: the seeder owns their defaults, so they can never be
     * renamed or deleted. Only super-admin is fully permission-locked.
     *
     * @var list<string>
     */
    public const BUILTIN_ROLES = ['super-admin', 'status-manager', 'status-viewer'];

    public function index(): View
    {
        $this->authorize('status.users.manage');

        return view('admin.status.roles.index', [
            'roles' => Role::withCount('users')->with('permissions')->orderBy('name')->get(),
            'groupedPermissions' => self::groupedPermissions(),
        ]);
    }

    /**
     * All known permissions grouped by area for the checkbox matrix.
     *
     * @return array<string, list<string>>
     */
    public static function groupedPermissions(): array
    {
        $grouped = [];

        foreach (RolesAndPermissionsSeeder::PERMISSIONS as $name) {
            $parts = explode('.', $name, 3);
            $grouped[$parts[1] ?? 'general'][] = $name;
        }

        ksort($grouped);

        return $grouped;
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('status.users.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:roles,name', Rule::notIn(self::BUILTIN_ROLES)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(RolesAndPermissionsSeeder::PERMISSIONS)],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        StatusAuditLog::record('role.created', null, null, ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()]);

        return redirect()->route('admin.status.roles.index')->with('status', "Role [{$role->name}] created.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('status.users.manage');

        $isBuiltin = in_array($role->name, self::BUILTIN_ROLES, true);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('roles', 'name')->ignore($role->id),
                $isBuiltin ? Rule::in([$role->name]) : Rule::notIn(self::BUILTIN_ROLES),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(RolesAndPermissionsSeeder::PERMISSIONS)],
        ]);

        // Super-admin always keeps every permission: stripping it could
        // lock every admin out of user management itself.
        $permissions = $role->name === 'super-admin'
            ? RolesAndPermissionsSeeder::PERMISSIONS
            : ($validated['permissions'] ?? []);

        $old = ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()];

        if (! $isBuiltin) {
            $role->name = $validated['name'];
            $role->save();
        }

        $role->syncPermissions($permissions);

        StatusAuditLog::record('role.updated', null, $old, ['name' => $role->name, 'permissions' => $permissions]);

        return redirect()->route('admin.status.roles.index')->with('status', "Role [{$role->name}] updated.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('status.users.manage');

        if (in_array($role->name, self::BUILTIN_ROLES, true)) {
            return redirect()->route('admin.status.roles.index')
                ->with('error', "Role [{$role->name}] is built in and cannot be deleted.");
        }

        if (User::role($role->name)->exists()) {
            return redirect()->route('admin.status.roles.index')
                ->with('error', "Role [{$role->name}] still has admins. Reassign them first.");
        }

        StatusAuditLog::record('role.deleted', null, ['name' => $role->name], null);

        $role->delete();

        return redirect()->route('admin.status.roles.index')->with('status', 'Role deleted.');
    }
}
