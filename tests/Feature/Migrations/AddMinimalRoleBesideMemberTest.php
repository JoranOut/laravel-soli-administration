<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The migration's real path only runs when a `member` role exists, which never
 * happens under migrate:fresh — so it shipped untested and failed on the first
 * production deploy with an empty pivot column name.
 */
function runMinimalRoleMigration(): object
{
    return require base_path('database/migrations/2026_09_04_100001_add_minimal_role_beside_member.php');
}

function makeMemberRoleWithHolders(int $holders = 2): Role
{
    $member = Role::findOrCreate('member');
    $member->givePermissionTo(Permission::findOrCreate('relaties.view'));
    $member->givePermissionTo(Permission::findOrCreate('contact.view'));

    User::factory()->count($holders)->create()->each(fn (User $u) => $u->assignRole($member));

    return $member;
}

test('the migration copies member onto a new minimal role', function () {
    $member = makeMemberRoleWithHolders(3);

    runMinimalRoleMigration()->up();

    $minimal = Role::findByName('minimal');
    expect($minimal)->not->toBeNull();

    expect($minimal->permissions->pluck('name')->sort()->values()->toArray())
        ->toBe(['contact.view', 'relaties.view']);

    // Same holders, and member keeps every one of them: that is what lets the
    // previous release keep working between migrate and the swap.
    expect(User::role('minimal')->count())->toBe(3);
    expect(User::role('member')->count())->toBe(3);
    expect(Role::findByName('member')->id)->toBe($member->id);
});

test('the migration is a no-op without a member role', function () {
    runMinimalRoleMigration()->up();

    expect(Role::where('name', 'minimal')->exists())->toBeFalse();
});

test('the migration can run twice', function () {
    makeMemberRoleWithHolders(2);

    $migration = runMinimalRoleMigration();
    $migration->up();
    $migration->up();

    expect(Role::where('name', 'minimal')->count())->toBe(1);
    expect(User::role('minimal')->count())->toBe(2);

    $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
    $minimalId = Role::findByName('minimal')->id;

    expect(DB::table(config('permission.table_names.role_has_permissions'))
        ->where($roleKey, $minimalId)->count())->toBe(2);
});

test('down removes minimal and leaves member untouched', function () {
    makeMemberRoleWithHolders(2);

    $migration = runMinimalRoleMigration();
    $migration->up();
    $migration->down();

    expect(Role::where('name', 'minimal')->exists())->toBeFalse();
    expect(User::role('member')->count())->toBe(2);
});
