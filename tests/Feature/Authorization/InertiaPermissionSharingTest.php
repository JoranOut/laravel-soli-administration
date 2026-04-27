<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('authenticated user receives permissions and roles via inertia', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('auth.permissions')
        ->has('auth.roles')
        ->where('auth.roles', ['admin'])
    );
});

test('bestuur user receives only view permissions', function () {
    $user = User::factory()->create();
    $user->assignRole('bestuur');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.roles', ['bestuur'])
        ->has('auth.permissions')
        ->where('auth.permissions', function ($permissions) {
            $perms = collect($permissions);

            return $perms->contains('relaties.view')
                && $perms->contains('instrumenten.view')
                && ! $perms->contains('relaties.create')
                && ! $perms->contains('relaties.edit')
                && ! $perms->contains('users.view');
        })
    );
});

test('ledenadministratie user receives all permissions except users', function () {
    $user = User::factory()->create();
    $user->assignRole('ledenadministratie');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.roles', ['ledenadministratie'])
        ->has('auth.permissions')
        ->where('auth.permissions', function ($permissions) {
            $perms = collect($permissions);

            return $perms->contains('relaties.view')
                && $perms->contains('relaties.create')
                && $perms->contains('instrumenten.edit')
                && ! $perms->contains('users.view');
        })
    );
});

test('guest receives empty permissions and roles', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.permissions', [])
        ->where('auth.roles', [])
    );
});
