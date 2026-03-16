<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');

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
            ]);

        $roles = Role::all()->pluck('name')->toArray();

        return Inertia::render('admin/users', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['search']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['roles' => __('You cannot edit your own roles.')]);
        }

        $rolesTable = config('permission.table_names.roles');

        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', "exists:{$rolesTable},name"],
        ]);

        if ($user->hasRole('admin') && ! in_array('admin', $validated['roles'])) {
            $adminCount = User::role('admin')->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['roles' => __('Cannot remove the last admin.')]);
            }
        }

        $user->syncRoles($validated['roles']);

        return back();
    }
}
