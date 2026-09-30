<?php

namespace Tests\Feature\Status;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StatusSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, StatusSettingSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
    }

    public function test_super_admin_can_crud_admins(): void
    {
        $this->actingAs($this->admin)->get('/admin/status/users')->assertOk()->assertSee('New admin');

        $this->actingAs($this->admin)->post('/admin/status/users', [
            'name' => 'New Manager',
            'email' => 'manager@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'role' => 'status-manager',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/status/users');

        $user = User::where('email', 'manager@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('status-manager'));
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));

        $this->actingAs($this->admin)->put("/admin/status/users/{$user->id}", [
            'name' => 'Renamed Manager',
            'email' => 'manager@example.com',
            'role' => 'status-viewer',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed Manager', $user->fresh()->name);
        $this->assertTrue($user->fresh()->hasRole('status-viewer'));

        $this->actingAs($this->admin)->delete("/admin/status/users/{$user->id}")->assertRedirect('/admin/status/users');
        $this->assertNull(User::find($user->id));
    }

    public function test_user_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->admin)->post('/admin/status/users', [
            'name' => 'Dup',
            'email' => $this->admin->email,
            'password' => 'short',
            'password_confirmation' => 'mismatch',
            'role' => 'nope',
        ])->assertSessionHasErrors(['email', 'password', 'role']);
    }

    public function test_manager_and_viewer_cannot_manage_admins_or_roles(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('status-manager');
        $viewer = User::factory()->create();
        $viewer->assignRole('status-viewer');

        foreach ([$manager, $viewer] as $user) {
            $this->actingAs($user)->get('/admin/status/users')->assertForbidden();
            $this->actingAs($user)->post('/admin/status/users', [])->assertForbidden();
            $this->actingAs($user)->get('/admin/status/roles')->assertForbidden();
            $this->actingAs($user)->post('/admin/status/roles', [])->assertForbidden();
            $this->actingAs($user)->put('/admin/status/profile', [])->assertSessionHasErrors(['name', 'email']);
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/status/users')->assertRedirect('/login');
        $this->get('/admin/status/roles')->assertRedirect('/login');
        $this->get('/admin/status/profile')->assertRedirect('/login');
    }

    public function test_admin_cannot_delete_self_or_last_super_admin_or_change_own_role(): void
    {
        // Self-delete is refused.
        $this->actingAs($this->admin)->delete("/admin/status/users/{$this->admin->id}")
            ->assertRedirect('/admin/status/users')->assertSessionHas('error');
        $this->assertNotNull(User::find($this->admin->id));

        // Own role change is refused.
        $this->actingAs($this->admin)->put("/admin/status/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'status-viewer',
        ])->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->hasRole('super-admin'));

        // Deleting the last super-admin is refused.
        $other = User::factory()->create();
        $other->assignRole('status-viewer');
        $this->actingAs($this->admin)->delete("/admin/status/users/{$other->id}")->assertRedirect();
        $this->actingAs($other)->delete("/admin/status/users/{$this->admin->id}")->assertForbidden();

        // With two super-admins, deleting one works.
        $second = User::factory()->create();
        $second->assignRole('super-admin');
        $this->actingAs($this->admin)->delete("/admin/status/users/{$second->id}")
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(User::find($second->id));
    }

    public function test_super_admin_can_manage_roles(): void
    {
        $this->actingAs($this->admin)->get('/admin/status/roles')->assertOk()->assertSee('New role');

        $this->actingAs($this->admin)->post('/admin/status/roles', [
            'name' => 'partner-support',
            'permissions' => ['status.view', 'status.services.view'],
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/status/roles');

        $role = Role::where('name', 'partner-support')->firstOrFail();
        $this->assertEqualsCanonicalizing(['status.view', 'status.services.view'], $role->permissions->pluck('name')->all());

        $this->actingAs($this->admin)->put("/admin/status/roles/{$role->id}", [
            'name' => 'partner-support',
            'permissions' => ['status.view'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['status.view'], $role->fresh()->permissions->pluck('name')->all());

        $this->actingAs($this->admin)->delete("/admin/status/roles/{$role->id}")->assertRedirect();
        $this->assertNull(Role::where('name', 'partner-support')->first());
    }

    public function test_builtin_roles_are_protected(): void
    {
        $superAdmin = Role::where('name', 'super-admin')->firstOrFail();

        // Cannot rename or strip a built-in role.
        $this->actingAs($this->admin)->put("/admin/status/roles/{$superAdmin->id}", [
            'name' => 'renamed',
            'permissions' => ['status.view'],
        ])->assertSessionHasErrors('name');

        // Super-admin keeps every permission even if none are submitted.
        $this->actingAs($this->admin)->put("/admin/status/roles/{$superAdmin->id}", [
            'name' => 'super-admin',
            'permissions' => [],
        ])->assertSessionHasNoErrors();
        $this->assertCount(count(RolesAndPermissionsSeeder::PERMISSIONS), $superAdmin->fresh()->permissions);

        // Built-ins cannot be deleted.
        $this->actingAs($this->admin)->delete("/admin/status/roles/{$superAdmin->id}")
            ->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull(Role::where('name', 'super-admin')->first());

        // Roles with admins cannot be deleted.
        $manager = Role::where('name', 'status-manager')->firstOrFail();
        $user = User::factory()->create();
        $user->assignRole('status-manager');
        $this->actingAs($this->admin)->delete("/admin/status/roles/{$manager->id}")
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_role_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->admin)->post('/admin/status/roles', [
            'name' => 'super-admin',
            'permissions' => ['status.nonexistent'],
        ])->assertSessionHasErrors(['name', 'permissions.0']);
    }

    public function test_profile_update_and_password_change(): void
    {
        $this->actingAs($this->admin)->get('/admin/status/profile')->assertOk()->assertSee('Profile information');

        $this->actingAs($this->admin)->put('/admin/status/profile', [
            'name' => 'Renamed Admin',
            'email' => 'renamed@example.com',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/status/profile');

        $this->assertSame('Renamed Admin', $this->admin->fresh()->name);
        $this->assertSame('renamed@example.com', $this->admin->fresh()->email);

        $this->actingAs($this->admin)->put('/admin/status/profile/password', [
            'current_password' => 'password',
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass-1', $this->admin->fresh()->password));

        // Wrong current password is rejected and the password is unchanged.
        $this->actingAs($this->admin)->put('/admin/status/profile/password', [
            'current_password' => 'wrong',
            'password' => 'another-pass-1',
            'password_confirmation' => 'another-pass-1',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('brand-new-pass-1', $this->admin->fresh()->password));
    }
}
