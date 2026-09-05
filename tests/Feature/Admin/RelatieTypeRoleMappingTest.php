<?php

use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\RelatieTypeRoleMapping;
use App\Models\User;
use App\Services\DerivedRoleSyncService;
use Database\Seeders\RelatieTypeRoleMappingSeeder;
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
    $member = User::factory()->create()->assignRole('minimal');

    $this->actingAs($member)
        ->get('/admin/relatie-type-rollen')
        ->assertForbidden();
});

test('guest is redirected from the relatie type roles page', function () {
    $this->get('/admin/relatie-type-rollen')->assertRedirect('/login');
});

test('the page exposes one role per type', function () {
    $admin = User::factory()->create()->assignRole('admin');

    RelatieTypeRoleMapping::create([
        'relatie_type_id' => $this->bestuurType->id,
        'role_id' => $this->bestuurRole->id,
    ]);

    $this->actingAs($admin)
        ->get('/admin/relatie-type-rollen')
        ->assertInertia(function ($page) {
            $rows = collect($page->toArray()['props']['relatieTypes']);
            $bestuur = $rows->firstWhere('id', $this->bestuurType->id);

            expect($bestuur)->toHaveKey('role_id');
            expect($bestuur['role_id'])->toBe($this->bestuurRole->id);
            expect($rows->firstWhere('naam', 'donateur')['role_id'])->toBeNull();
        });
});

test('several types may point to the same role', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $minimal = Role::where('name', 'minimal')->first();
    $lid = RelatieType::where('naam', 'lid')->first();
    $donateur = RelatieType::where('naam', 'donateur')->first();

    $this->actingAs($admin)
        ->put("/admin/relatie-type-rollen/{$lid->id}", ['role_id' => $minimal->id])
        ->assertRedirect();
    $this->actingAs($admin)
        ->put("/admin/relatie-type-rollen/{$donateur->id}", ['role_id' => $minimal->id])
        ->assertRedirect();

    $this->assertDatabaseCount('soli_relatie_type_role_mappings', 2);
});

test('editing one type leaves the other types alone', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $minimal = Role::where('name', 'minimal')->first();
    $lid = RelatieType::where('naam', 'lid')->first();

    RelatieTypeRoleMapping::create([
        'relatie_type_id' => $this->bestuurType->id,
        'role_id' => $this->bestuurRole->id,
    ]);

    // A second admin editing lid must not wipe the bestuur row
    $this->actingAs($admin)
        ->put("/admin/relatie-type-rollen/{$lid->id}", ['role_id' => $minimal->id])
        ->assertRedirect();

    $this->assertDatabaseHas('soli_relatie_type_role_mappings', [
        'relatie_type_id' => $this->bestuurType->id,
        'role_id' => $this->bestuurRole->id,
    ]);
    $this->assertDatabaseCount('soli_relatie_type_role_mappings', 2);
});

test('replacing the role for a type keeps one row', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $minimal = Role::where('name', 'minimal')->first();

    RelatieTypeRoleMapping::create([
        'relatie_type_id' => $this->bestuurType->id,
        'role_id' => $this->bestuurRole->id,
    ]);

    $this->actingAs($admin)
        ->put("/admin/relatie-type-rollen/{$this->bestuurType->id}", ['role_id' => $minimal->id])
        ->assertRedirect();

    $this->assertDatabaseCount('soli_relatie_type_role_mappings', 1);
    $this->assertDatabaseHas('soli_relatie_type_role_mappings', [
        'relatie_type_id' => $this->bestuurType->id,
        'role_id' => $minimal->id,
    ]);
});

test('admin can save a mapping', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->put("/admin/relatie-type-rollen/{$this->bestuurType->id}", ['role_id' => $this->bestuurRole->id])
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
        ->put("/admin/relatie-type-rollen/{$this->bestuurType->id}", ['role_id' => $this->bestuurRole->id])
        ->assertRedirect();

    expect($lid->fresh()->hasRole('bestuur'))->toBeTrue();
});

test('removing a mapping revokes the role it granted', function () {
    $admin = User::factory()->create()->assignRole('admin');

    RelatieTypeRoleMapping::create([
        'relatie_type_id' => $this->bestuurType->id,
        'role_id' => $this->bestuurRole->id,
    ]);

    // Someone who earned the role through the mapping
    $lid = User::factory()->create();
    Relatie::factory()
        ->create(['user_id' => $lid->id])
        ->types()->attach($this->bestuurType->id, ['van' => '2026-01-01']);
    $lid->assignRole('bestuur');

    $this->actingAs($admin)
        ->put("/admin/relatie-type-rollen/{$this->bestuurType->id}", ['role_id' => null])
        ->assertRedirect();

    $this->assertDatabaseCount('soli_relatie_type_role_mappings', 0);

    // The mapping is gone, so the role it handed out must be gone too
    expect($lid->fresh()->hasRole('bestuur'))->toBeFalse();
});

test('the nightly command also revokes a role whose mapping was removed', function () {
    $lid = User::factory()->create();
    Relatie::factory()
        ->create(['user_id' => $lid->id])
        ->types()->attach($this->bestuurType->id, ['van' => '2026-01-01']);
    $lid->assignRole('bestuur');

    // The bestuur mapping is gone but the table is configured: another type
    // still maps, so the command runs instead of bailing out.
    RelatieTypeRoleMapping::create([
        'relatie_type_id' => RelatieType::where('naam', 'lid')->first()->id,
        'role_id' => Role::where('name', 'minimal')->first()->id,
    ]);

    $this->artisan('roles:sync-derived')->assertSuccessful();

    expect($lid->fresh()->hasRole('bestuur'))->toBeFalse();
});

test('mapping a type to a never-managed role is refused', function (string $roleName) {
    $admin = User::factory()->create()->assignRole('admin');
    $role = Role::where('name', $roleName)->first();

    $this->actingAs($admin)
        ->put("/admin/relatie-type-rollen/{$this->bestuurType->id}", ['role_id' => $role->id])
        ->assertSessionHasErrors('role_id');

    $this->assertDatabaseCount('soli_relatie_type_role_mappings', 0);
})->with(['admin', 'ledenadministratie']);

test('the seeder is idempotent and overwrites a changed role', function () {
    $lid = RelatieType::where('naam', 'lid')->first();

    // A mapping the admin changed by hand, pointing at the wrong role
    RelatieTypeRoleMapping::create([
        'relatie_type_id' => $lid->id,
        'role_id' => $this->bestuurRole->id,
    ]);

    $this->seed(RelatieTypeRoleMappingSeeder::class);
    $this->seed(RelatieTypeRoleMappingSeeder::class);

    expect(RelatieTypeRoleMapping::where('relatie_type_id', $lid->id)->count())->toBe(1);
    $this->assertDatabaseHas('soli_relatie_type_role_mappings', [
        'relatie_type_id' => $lid->id,
        'role_id' => Role::where('name', 'minimal')->first()->id,
    ]);
});
