<?php

use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\RelatieTypeRoleMapping;
use App\Models\User;
use App\Services\DerivedRoleSyncService;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RelatieTypeSeeder::class);
    $this->withoutVite();

    $this->bestuurType = RelatieType::where('naam', 'bestuur')->first();
    $this->bestuurRole = Role::where('name', 'bestuur')->first();
});

test('admin can view the relatie type roles page', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/relatie-type-rollen')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/relatie-type-rollen')
            ->has('relatieTypes')
            ->has('roles')
            ->has('neverManaged')
        );
});

test('never-managed roles are not offered as mappable roles', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/relatie-type-rollen')
        ->assertInertia(function ($page) {
            $names = collect($page->toArray()['props']['roles'])->pluck('name');

            foreach (DerivedRoleSyncService::NEVER_MANAGED as $never) {
                expect($names)->not->toContain($never);
            }

            expect($names)->toContain('bestuur');
        });
});

test('non-admin gets 403 on the relatie type roles page', function () {
    $member = User::factory()->create()->assignRole('member');

    $this->actingAs($member)
        ->get('/admin/relatie-type-rollen')
        ->assertForbidden();
});

test('guest is redirected from the relatie type roles page', function () {
    $this->get('/admin/relatie-type-rollen')->assertRedirect('/login');
});

test('admin can save a mapping', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->put('/admin/relatie-type-rollen', [
            'mappings' => [
                ['relatie_type_id' => $this->bestuurType->id, 'role_id' => $this->bestuurRole->id],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('soli_relatie_type_role_mappings', [
        'relatie_type_id' => $this->bestuurType->id,
        'role_id' => $this->bestuurRole->id,
    ]);
});

test('saving a mapping applies it immediately', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $lid = User::factory()->create();
    Relatie::factory()
        ->create(['user_id' => $lid->id])
        ->types()->attach($this->bestuurType->id, ['van' => '2026-01-01']);

    expect($lid->hasRole('bestuur'))->toBeFalse();

    $this->actingAs($admin)
        ->put('/admin/relatie-type-rollen', [
            'mappings' => [
                ['relatie_type_id' => $this->bestuurType->id, 'role_id' => $this->bestuurRole->id],
            ],
        ])
        ->assertRedirect();

    expect($lid->fresh()->hasRole('bestuur'))->toBeTrue();
});

test('removing a mapping stops deriving the role', function () {
    $admin = User::factory()->create()->assignRole('admin');

    RelatieTypeRoleMapping::create([
        'relatie_type_id' => $this->bestuurType->id,
        'role_id' => $this->bestuurRole->id,
    ]);

    $this->actingAs($admin)
        ->put('/admin/relatie-type-rollen', ['mappings' => []])
        ->assertRedirect();

    $this->assertDatabaseCount('soli_relatie_type_role_mappings', 0);
});

test('mapping a type to a never-managed role is refused', function (string $roleName) {
    $admin = User::factory()->create()->assignRole('admin');
    $role = Role::where('name', $roleName)->first();

    $this->actingAs($admin)
        ->put('/admin/relatie-type-rollen', [
            'mappings' => [
                ['relatie_type_id' => $this->bestuurType->id, 'role_id' => $role->id],
            ],
        ])
        ->assertSessionHasErrors('mappings.0.role_id');

    $this->assertDatabaseCount('soli_relatie_type_role_mappings', 0);
})->with(['admin', 'ledenadministratie', 'member']);
