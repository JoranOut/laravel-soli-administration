<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index(): Response
    {
        $roles = Role::with('permissions')->get()->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->toArray(),
        ]);

        $permissions = Permission::all()->pluck('name')->toArray();

        return Inertia::render('admin/roles', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $permissionsTable = config('permission.table_names.permissions');

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', "exists:{$permissionsTable},name"],
        ]);

        $role->syncPermissions($validated['permissions']);

        return back();
    }
}
