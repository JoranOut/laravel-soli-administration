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

        $resources = ['relaties', 'onderdelen', 'instrumenten', 'instrumentsoorten', 'users'];
        $actions = ['view', 'create', 'edit', 'delete'];

        // Create permissions for each resource-action combination
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$resource}.{$action}");
            }
        }

        // Standalone permissions (no CRUD needed)
        Permission::findOrCreate('dashboard.view');
        Permission::findOrCreate('contact.view');

        // Splits "every relatie" from "my own record": both used to be
        // relaties.view, which is why the controllers had to test role names.
        Permission::findOrCreate('relaties.view.all');

        // The /admin authentication group: roles, users, links, activity log,
        // OAuth clients and the sync pages. Replaces the role:admin middleware.
        Permission::findOrCreate('beheer.manage');

        // Admin: all permissions
        Role::findOrCreate('admin')
            ->syncPermissions(Permission::all());

        // Bestuur: view-only on all resources (except users)
        Role::findOrCreate('bestuur')
            ->syncPermissions([
                'dashboard.view',
                'contact.view',
                'relaties.view',
                'relaties.view.all',
                'onderdelen.view',
                'instrumenten.view',
                'instrumentsoorten.view',
            ]);

        // Ledenadministratie: full CRUD on all resources except users, and not
        // the authentication pages either
        Role::findOrCreate('ledenadministratie')
            ->syncPermissions(
                Permission::where('name', 'not like', 'users.%')
                    ->where('name', '!=', 'beheer.manage')
                    ->pluck('name')
                    ->toArray()
            );

        // Contactpersoon: the contact page and nothing else
        Role::findOrCreate('contactpersoon')
            ->syncPermissions([
                'dashboard.view',
                'contact.view',
            ]);

        // Minimal: view own data only (enforced at policy level). Derived from
        // the lid, donateur and vrijwilliger types.
        Role::findOrCreate('minimal')
            ->syncPermissions([
                'relaties.view',
                'contact.view',
            ]);
    }
}
