<?php

use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\RelatieTypeRoleMapping;
use App\Models\User;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

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

// --- Derived roles ---

function mapBestuurType(): RelatieType
{
    test()->seed(RelatieTypeSeeder::class);

    $type = RelatieType::where('naam', 'bestuur')->first();

    RelatieTypeRoleMapping::create([
        'relatie_type_id' => $type->id,
        'role_id' => Role::where('name', 'bestuur')->first()->id,
    ]);

    return $type;
}

test('a mapped role cannot be assigned by hand', function () {
    mapBestuurType();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();
    $user->assignRole('member');

    $this->actingAs($admin)
        ->put("/admin/users/{$user->id}", ['roles' => ['bestuur']])
        ->assertSessionHasErrors('roles');

    expect($user->fresh()->hasRole('bestuur'))->toBeFalse();
});

test('changing the manual role keeps a derived role', function () {
    $type = mapBestuurType();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();
    $user->assignRole(['member', 'bestuur']);
    Relatie::factory()
        ->create(['user_id' => $user->id])
        ->types()->attach($type->id, ['van' => '2026-01-01']);

    $this->actingAs($admin)
        ->put("/admin/users/{$user->id}", ['roles' => ['ledenadministratie']])
        ->assertRedirect();

    $user->refresh();
    expect($user->hasRole('ledenadministratie'))->toBeTrue();
    expect($user->hasRole('bestuur'))->toBeTrue();
    expect($user->hasRole('member'))->toBeFalse();
});

test('the users page exposes derived roles separately', function () {
    $type = mapBestuurType();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();
    Relatie::factory()
        ->create(['user_id' => $user->id])
        ->types()->attach($type->id, ['van' => '2026-01-01']);

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertInertia(function ($page) use ($user) {
            $props = $page->toArray()['props'];

            expect($props['managedRoles'])->toContain('bestuur');

            $row = collect($props['users'])->firstWhere('id', $user->id);
            expect($row['derived_roles'])->toBe(['bestuur']);
        });
});
