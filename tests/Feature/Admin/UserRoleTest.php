<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

test('admin can view the users page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users')
            ->has('users')
            ->has('roles')
        );
});

test('admin can assign a role to a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();
    $user->assignRole('member');

    $this->actingAs($admin)
        ->put("/admin/users/{$user->id}", [
            'roles' => ['bestuur'],
        ])
        ->assertRedirect();

    $user->refresh();
    expect($user->hasRole('bestuur'))->toBeTrue();
    expect($user->hasRole('member'))->toBeFalse();
});

test('non-admin gets 403 on users page', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $this->actingAs($member)
        ->get('/admin/users')
        ->assertForbidden();
});
