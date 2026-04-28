<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('seeder creates all expected permissions', function () {
    $resources = ['relaties', 'onderdelen', 'instrumenten', 'instrumentsoorten', 'users'];
    $actions = ['view', 'create', 'edit', 'delete'];

    foreach ($resources as $resource) {
        foreach ($actions as $action) {
            expect(Permission::findByName("{$resource}.{$action}"))->not->toBeNull();
        }
    }

    expect(Permission::count())->toBe(20);
});

test('seeder creates all expected roles', function () {
    expect(Role::findByName('admin'))->not->toBeNull();
    expect(Role::findByName('bestuur'))->not->toBeNull();
    expect(Role::findByName('ledenadministratie'))->not->toBeNull();
    expect(Role::findByName('muziekbeheer'))->not->toBeNull();
    expect(Role::findByName('member'))->not->toBeNull();
    expect(Role::count())->toBe(5);
});

test('admin role has all permissions', function () {
    $admin = Role::findByName('admin');

    expect($admin->permissions->count())->toBe(20);
});

test('bestuur role has view-only permissions', function () {
    $bestuur = Role::findByName('bestuur');
    $permissionNames = $bestuur->permissions->pluck('name')->toArray();

    $expected = [
        'relaties.view',
        'onderdelen.view',
        'instrumenten.view',
        'instrumentsoorten.view',
    ];

    expect($permissionNames)->toEqualCanonicalizing($expected);
});

test('ledenadministratie role has all permissions except users', function () {
    $ledenadmin = Role::findByName('ledenadministratie');
    $permissionNames = $ledenadmin->permissions->pluck('name')->toArray();

    $expected = [
        'relaties.view', 'relaties.create', 'relaties.edit', 'relaties.delete',
        'onderdelen.view', 'onderdelen.create', 'onderdelen.edit', 'onderdelen.delete',
        'instrumenten.view', 'instrumenten.create', 'instrumenten.edit', 'instrumenten.delete',
        'instrumentsoorten.view', 'instrumentsoorten.create', 'instrumentsoorten.edit', 'instrumentsoorten.delete',
    ];

    expect($permissionNames)->toEqualCanonicalizing($expected);
    expect($permissionNames)->not->toContain('users.view');
    expect($permissionNames)->not->toContain('users.create');
    expect($permissionNames)->not->toContain('users.edit');
    expect($permissionNames)->not->toContain('users.delete');
});

test('muziekbeheer role has instrumentsoorten permissions', function () {
    $muziekbeheer = Role::findByName('muziekbeheer');
    $permissionNames = $muziekbeheer->permissions->pluck('name')->toArray();

    $expected = [
        'instrumentsoorten.view',
        'instrumentsoorten.create',
        'instrumentsoorten.edit',
        'instrumentsoorten.delete',
    ];

    expect($permissionNames)->toEqualCanonicalizing($expected);
});

test('member role has correct permissions', function () {
    $member = Role::findByName('member');
    $permissionNames = $member->permissions->pluck('name')->toArray();

    expect($permissionNames)->toEqualCanonicalizing(['relaties.view']);
});

test('user can be assigned a role', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->can('relaties.view'))->toBeTrue();
    expect($user->can('users.delete'))->toBeTrue();
});

test('seeder is idempotent', function () {
    // Run seeder again
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::count())->toBe(20);
    expect(Role::count())->toBe(5);
});
