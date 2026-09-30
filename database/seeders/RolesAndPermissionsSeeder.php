<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * All status permissions (final-plan §2 / plan #68).
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'status.view',

        'status.services.view',
        'status.services.create',
        'status.services.update',
        'status.services.delete',

        'status.monitoring.view',
        'status.monitoring.run',

        'status.incidents.view',
        'status.incidents.create',
        'status.incidents.update',
        'status.incidents.delete',

        'status.maintenance.view',
        'status.maintenance.create',
        'status.maintenance.update',
        'status.maintenance.delete',

        'status.notifications.manage',
        'status.settings.manage',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(self::PERMISSIONS);

        $manager = Role::firstOrCreate(['name' => 'status-manager', 'guard_name' => 'web']);
        $manager->syncPermissions(array_values(array_diff(self::PERMISSIONS, [
            'status.settings.manage',
        ])));

        $viewer = Role::firstOrCreate(['name' => 'status-viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions([
            'status.view',
            'status.services.view',
            'status.monitoring.view',
            'status.incidents.view',
            'status.maintenance.view',
        ]);

        // Local-only bootstrap login. Never seeded outside local.
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Status Admin', 'password' => Hash::make('password')],
        );

        if (! $admin->hasRole('super-admin')) {
            $admin->assignRole('super-admin');
        }
    }
}
