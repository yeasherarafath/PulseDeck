<?php

namespace App\Http\Controllers\Admin\Status;

use App\Http\Controllers\Controller;
use App\Models\Status\StatusAuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use AuthorizesRequests;

    public function edit(): View
    {
        return view('admin.status.profile.edit', [
            'user' => request()->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $old = ['name' => $user->name, 'email' => $user->email];

        $user->forceFill([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
        ])->save();

        StatusAuditLog::record('profile.updated', $user->fresh(), $old, ['name' => $user->name, 'email' => $user->email]);

        return redirect()->route('admin.status.profile')->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'The current password is incorrect.',
        ]);

        $request->user()->forceFill(['password' => Hash::make($validated['password'])])->save();

        StatusAuditLog::record('profile.password_changed', $request->user()->fresh(), null, null);

        return redirect()->route('admin.status.profile')->with('status', 'Password changed.');
    }
}
