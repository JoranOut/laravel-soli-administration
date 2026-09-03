<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DerivedRoleSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    public function index(Request $request, DerivedRoleSyncService $service): Response
    {
        $search = $request->input('search');

        $managedRoles = $service->managedRoleNames();
        $derivedRoles = $service->derivedRolesForAllUsers();

        $users = User::with('roles')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'derived_roles' => $derivedRoles[$user->id] ?? [],
            ]);

        $roles = Role::all()->pluck('name')->toArray();

        return Inertia::render('admin/users', [
            'users' => $users,
            'roles' => $roles,
            'managedRoles' => $managedRoles,
            'filters' => $request->only(['search']),
        ]);
    }

    public function update(Request $request, User $user, DerivedRoleSyncService $service)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['roles' => __('You cannot edit your own roles.')]);
        }

        $rolesTable = config('permission.table_names.roles');

        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', "exists:{$rolesTable},name"],
        ]);

        $managedRoles = $service->managedRoleNames();
        $handAssigned = array_intersect($validated['roles'], $managedRoles);

        if ($handAssigned !== []) {
            return back()->withErrors(['roles' => __('The role :role follows from a relatie type and cannot be assigned by hand.', [
                'role' => implode(', ', $handAssigned),
            ])]);
        }

        if ($user->hasRole('admin') && ! in_array('admin', $validated['roles'])) {
            $adminCount = User::role('admin')->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['roles' => __('Cannot remove the last admin.')]);
            }
        }

        $oldRoles = $user->roles->pluck('name')->toArray();

        // Keep the roles this user earns through relatie types; syncRoles would
        // drop them and the nightly command would silently put them back.
        $derived = array_values(array_intersect($oldRoles, $managedRoles));

        $user->syncRoles([...$validated['roles'], ...$derived]);

        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties([
                'old' => $oldRoles,
                'new' => $validated['roles'],
            ])
            ->log("Roles changed for {$user->name}");

        return back();
    }
}
