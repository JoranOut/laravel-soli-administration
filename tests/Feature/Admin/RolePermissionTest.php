<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

test('admin can view the roles page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/roles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/roles')
            ->has('roles')
            ->has('permissions')
        );
});

test('admin can update role permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $role = Role::findByName('member');

    $this->actingAs($admin)
        ->put("/admin/roles/{$role->id}", [
            'permissions' => ['relaties.view', 'relaties.edit'],
        ])
        ->assertRedirect();

    $role->refresh();
    expect($role->permissions->pluck('name')->toArray())
        ->toEqualCanonicalizing(['relaties.view', 'relaties.edit']);
});

test('non-admin gets 403 on roles page', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $this->actingAs($member)
        ->get('/admin/roles')
        ->assertForbidden();
});
