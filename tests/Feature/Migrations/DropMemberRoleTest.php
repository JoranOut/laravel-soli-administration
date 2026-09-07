<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function runDropMemberMigration(): object
{
    return require base_path('database/migrations/2026_09_07_100001_drop_member_role.php');
}

function expandedMemberAndMinimal(int $holders = 2): array
{
    $member = Role::findOrCreate('member');
    $minimal = Role::findOrCreate('minimal');

    foreach (['relaties.view', 'contact.view'] as $permission) {
        $member->givePermissionTo(Permission::findOrCreate($permission));
        $minimal->givePermissionTo(Permission::findOrCreate($permission));
    }

    $users = User::factory()->count($holders)->create();
    $users->each(fn (User $u) => $u->assignRole(['member', 'minimal']));

    return [$member, $minimal, $users];
}

test('the migration drops member and leaves minimal holders intact', function () {
    [, , $users] = expandedMemberAndMinimal(3);

    runDropMemberMigration()->up();

    expect(Role::where('name', 'member')->exists())->toBeFalse();
    expect(User::role('minimal')->count())->toBe(3);
    expect($users->first()->fresh()->can('relaties.view'))->toBeTrue();
});

test('the migration refuses when a holder lacks minimal', function () {
    expandedMemberAndMinimal(2);

    // Someone the expand step never reached
    $stragglar = User::factory()->create();
    $stragglar->assignRole('member');

    expect(fn () => runDropMemberMigration()->up())
        ->toThrow(RuntimeException::class, "do not have 'minimal'");

    expect(Role::where('name', 'member')->exists())->toBeTrue();
    expect($stragglar->fresh()->hasRole('member'))->toBeTrue();
});

test('the migration refuses when minimal does not exist', function () {
    $member = Role::findOrCreate('member');
    User::factory()->create()->assignRole($member);

    expect(fn () => runDropMemberMigration()->up())
        ->toThrow(RuntimeException::class, "'minimal' does not exist");

    expect(Role::where('name', 'member')->exists())->toBeTrue();
});

test('the migration is a no-op when member is already gone', function () {
    Role::findOrCreate('minimal');

    runDropMemberMigration()->up();

    expect(Role::where('name', 'member')->exists())->toBeFalse();
});

test('down rebuilds member from the current minimal holders', function () {
    expandedMemberAndMinimal(2);

    $migration = runDropMemberMigration();
    $migration->up();
    $migration->down();

    expect(Role::where('name', 'member')->exists())->toBeTrue();
    expect(User::role('member')->count())->toBe(2);
    expect(Role::findByName('member')->permissions->pluck('name')->sort()->values()->toArray())
        ->toBe(['contact.view', 'relaties.view']);
});
