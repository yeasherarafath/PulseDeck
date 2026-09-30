<?php

namespace App\Http\Controllers\Admin\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('status.users.manage');

        return view('admin.status.users.index', [
            'users' => User::with('roles')->orderBy('name')->paginate(25),
            'roles' => Role::orderBy('name')->pluck('name')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('status.users.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in(Role::orderBy('name')->pluck('name')->all())],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        StatusAuditLog::record('user.created', $user, null, ['email' => $user->email, 'role' => $validated['role']]);

        return redirect()->route('admin.status.users.index')->with('status', "Admin [{$user->email}] created.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('status.users.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in(Role::orderBy('name')->pluck('name')->all())],
        ]);

        // Nobody can strip their own role: that would lock them out (or
        // silently escalate a session). Edit another admin instead.
        if ($user->is($request->user()) && ! $user->hasRole($validated['role'])) {
            return redirect()->route('admin.status.users.index')
                ->with('error', 'You cannot change your own role.');
        }

        $old = ['name' => $user->name, 'email' => $user->email, 'roles' => $user->getRoleNames()->all()];

        $user->forceFill([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        StatusAuditLog::record('user.updated', $user->fresh(), $old, ['name' => $user->name, 'email' => $user->email, 'roles' => [$validated['role']]]);

        return redirect()->route('admin.status.users.index')->with('status', "Admin [{$user->email}] updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('status.users.manage');

        if ($user->is($request->user())) {
            return redirect()->route('admin.status.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        // The app must always keep at least one super-admin.
        if ($user->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            return redirect()->route('admin.status.users.index')
                ->with('error', "Admin [{$user->email}] is the last super-admin and cannot be deleted.");
        }

        StatusAuditLog::record('user.deleted', $user, ['email' => $user->email, 'roles' => $user->getRoleNames()->all()], null);

        $user->delete();

        return redirect()->route('admin.status.users.index')->with('status', 'Admin deleted.');
    }
}
