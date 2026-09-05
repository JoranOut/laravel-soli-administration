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

    $this->service = app(DerivedRoleSyncService::class);
    $this->bestuurType = RelatieType::where('naam', 'bestuur')->first();

    $this->mapBestuur = function (string $roleName = 'bestuur') {
        return RelatieTypeRoleMapping::create([
            'relatie_type_id' => $this->bestuurType->id,
            'role_id' => Role::where('name', $roleName)->first()->id,
        ]);
    };

    $this->relatieFor = function (User $user, ?string $van = '2026-01-01', ?string $tot = null, array $relatieAttributes = []) {
        $relatie = Relatie::factory()->create(['user_id' => $user->id, ...$relatieAttributes]);

        if ($van !== null) {
            $relatie->types()->attach($this->bestuurType->id, ['van' => $van, 'tot' => $tot]);
        }

        return $relatie;
    };
});

test('an active bestuur type grants the bestuur role', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    ($this->relatieFor)($user);

    $this->service->syncUser($user->load('roles'));

    expect($user->fresh()->hasRole('bestuur'))->toBeTrue();
});

test('a type whose tot has passed revokes the role', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    $user->assignRole('bestuur');
    ($this->relatieFor)($user, '2020-01-01', now()->subDay()->toDateString());

    $this->service->syncUser($user->load('roles'));

    expect($user->fresh()->hasRole('bestuur'))->toBeFalse();
});

test('a type whose tot is today still grants the role', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    ($this->relatieFor)($user, '2020-01-01', now()->toDateString());

    $this->service->syncUser($user->load('roles'));

    expect($user->fresh()->hasRole('bestuur'))->toBeTrue();
});

test('a type whose van has not started yet grants nothing', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    ($this->relatieFor)($user, now()->addDay()->toDateString());

    $this->service->syncUser($user->load('roles'));

    expect($user->fresh()->hasRole('bestuur'))->toBeFalse();
});

test('a type on an inactive relatie grants nothing', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    ($this->relatieFor)($user, '2020-01-01', null, ['actief' => false]);

    $this->service->syncUser($user->load('roles'));

    expect($user->fresh()->hasRole('bestuur'))->toBeFalse();
});

test('a hand-granted admin survives a sync that revokes bestuur', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    $user->assignRole(['admin', 'bestuur']);
    ($this->relatieFor)($user, null);

    $this->service->syncUser($user->load('roles'));

    $user->refresh();
    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->hasRole('bestuur'))->toBeFalse();
});

test('a role no type maps to is revoked', function () {
    ($this->mapBestuur)();
    Role::create(['name' => 'commissie']);

    $user = User::factory()->create();
    $user->assignRole('commissie');
    ($this->relatieFor)($user, null);

    $this->service->syncUser($user->load('roles'));

    // Nothing maps to commissie, so nobody can hold it: every role outside
    // NEVER_MANAGED has to come from a type.
    expect($user->fresh()->hasRole('commissie'))->toBeFalse();
});

test('a never-managed role is left alone', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    $user->assignRole('ledenadministratie');
    ($this->relatieFor)($user, null);

    $this->service->syncUser($user->load('roles'));

    expect($user->fresh()->hasRole('ledenadministratie'))->toBeTrue();
});

test('a never-managed role cannot become managed even when mapped', function (string $roleName) {
    ($this->mapBestuur)($roleName);

    expect($this->service->managedRoleNames())->not->toContain($roleName);

    $user = User::factory()->create();
    $user->assignRole($roleName);
    ($this->relatieFor)($user, null);

    $this->service->syncUser($user->load('roles'));

    expect($user->fresh()->hasRole($roleName))->toBeTrue();
})->with(['admin', 'ledenadministratie']);

test('a type held through a second relatie counts', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    ($this->relatieFor)($user, null);
    ($this->relatieFor)($user);

    $this->service->syncUser($user->load('roles'));

    expect($user->fresh()->hasRole('bestuur'))->toBeTrue();
});

test('the role stays while a second mapped type is still active', function () {
    ($this->mapBestuur)();
    $dirigentType = RelatieType::where('naam', 'dirigent')->first();
    RelatieTypeRoleMapping::create([
        'relatie_type_id' => $dirigentType->id,
        'role_id' => Role::where('name', 'bestuur')->first()->id,
    ]);

    $user = User::factory()->create();
    $relatie = Relatie::factory()->create(['user_id' => $user->id]);
    $relatie->types()->attach($this->bestuurType->id, ['van' => '2020-01-01', 'tot' => now()->subDay()->toDateString()]);
    $relatie->types()->attach($dirigentType->id, ['van' => '2020-01-01']);

    $this->service->syncUser($user->load('roles'));

    expect($user->fresh()->hasRole('bestuur'))->toBeTrue();
});

test('a user without a relatie keeps a never-managed role', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    $user->assignRole('ledenadministratie');

    $result = $this->service->syncUser($user->load('roles'));

    expect($result)->toBe(['added' => [], 'removed' => []]);
    expect($user->fresh()->hasRole('ledenadministratie'))->toBeTrue();
});

test('a user without a relatie loses a derived role', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    $user->assignRole('minimal');

    $this->service->syncUser($user->load('roles'));

    expect($user->fresh()->hasRole('minimal'))->toBeFalse();
});

test('the command does nothing while no type is mapped', function () {
    $user = User::factory()->create();
    $user->assignRole('bestuur');
    ($this->relatieFor)($user, null);

    // An unconfigured system must not strip roles: right after a deploy the
    // mapping table is empty, and the daily run would empty every account.
    $this->artisan('roles:sync-derived')
        ->expectsOutputToContain('nothing to sync')
        ->assertSuccessful();

    expect($user->fresh()->hasRole('bestuur'))->toBeTrue();
});

test('dry run reports changes without applying them', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    ($this->relatieFor)($user);

    $changed = $this->service->syncAll(null, true);

    expect($changed)->toBe(1);
    expect($user->fresh()->hasRole('bestuur'))->toBeFalse();
});

test('the command syncs every user', function () {
    ($this->mapBestuur)();
    $user = User::factory()->create();
    ($this->relatieFor)($user);

    $this->artisan('roles:sync-derived')
        ->expectsOutputToContain($user->email)
        ->assertSuccessful();

    expect($user->fresh()->hasRole('bestuur'))->toBeTrue();
});

test('derivedRolesForAllUsers matches the per-user result', function () {
    ($this->mapBestuur)();
    $earning = User::factory()->create();
    ($this->relatieFor)($earning);
    $other = User::factory()->create();
    ($this->relatieFor)($other, '2020-01-01', now()->subDay()->toDateString());

    $map = $this->service->derivedRolesForAllUsers();

    expect($map[$earning->id])->toBe(['bestuur']);
    expect($map)->not->toHaveKey($other->id);
});

test('an active contactpersoon type grants the contactpersoon role', function () {
    $this->seed(RelatieTypeRoleMappingSeeder::class);

    $type = RelatieType::where('naam', 'contactpersoon')->first();
    $user = User::factory()->create();
    Relatie::factory()
        ->create(['user_id' => $user->id])
        ->types()->attach($type->id, ['van' => '2026-01-01']);

    $this->service->syncUser($user->load('roles'));

    $user->refresh();
    expect($user->hasRole('contactpersoon'))->toBeTrue();
    expect($user->can('contact.view'))->toBeTrue();
    // Their own record, but not everyone's, and not the statistics dashboard
    expect($user->can('relaties.view'))->toBeTrue();
    expect($user->can('relaties.view.all'))->toBeFalse();
    expect($user->can('dashboard.view'))->toBeFalse();
});
