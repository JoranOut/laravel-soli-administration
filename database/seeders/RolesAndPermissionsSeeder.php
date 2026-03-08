<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $resources = ['relaties', 'onderdelen', 'instrumenten', 'financieel', 'users'];
        $actions = ['view', 'create', 'edit', 'delete'];

        // Create permissions for each resource-action combination
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$resource}.{$action}");
            }
        }

        // Admin: all permissions
        Role::findOrCreate('admin')
            ->syncPermissions(Permission::all());

        // Bestuur: view-only on all resources (except users)
        Role::findOrCreate('bestuur')
            ->syncPermissions([
                'relaties.view',
                'onderdelen.view',
                'instrumenten.view',
                'financieel.view',
            ]);

        // Ledenadministratie: full CRUD on all resources except users
        Role::findOrCreate('ledenadministratie')
            ->syncPermissions(
                Permission::where('name', 'not like', 'users.%')->pluck('name')->toArray()
            );

        // Member: view own data only (enforced at policy level)
        Role::findOrCreate('member')
            ->syncPermissions([
                'relaties.view',
            ]);
    }
}
