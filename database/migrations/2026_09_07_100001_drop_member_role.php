<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Contract step of renaming `member` to `minimal`.
     *
     * The expand step (2026_09_04_100001) created `minimal` beside `member` and
     * copied its permissions and every assignment, so the running release has
     * not looked at `member` since. Dropping it closes the rollback path to the
     * release before that one, which still called hasRole('member') — that is
     * why this waits for a separate deploy.
     */
    public function up(): void
    {
        $this->drop('member', 'minimal');
    }

    /**
     * Recreates `member` from the current `minimal` holders.
     *
     * Reversible within this release, but not a byte-for-byte restore: the
     * derived-role sync may have changed who holds `minimal` since the drop,
     * and `member` is rebuilt from whoever holds it now.
     */
    public function down(): void
    {
        $rolesTable = config('permission.table_names.roles');
        $pivotTable = config('permission.table_names.model_has_roles');
        $permissionPivot = config('permission.table_names.role_has_permissions');
        $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $permissionKey = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

        $minimal = DB::table($rolesTable)->where('name', 'minimal')->first();

        if (! $minimal || DB::table($rolesTable)->where('name', 'member')->exists()) {
            return;
        }

        $memberId = DB::table($rolesTable)->insertGetId([
            'name' => 'member',
            'guard_name' => $minimal->guard_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (DB::table($permissionPivot)->where($roleKey, $minimal->id)->pluck($permissionKey) as $permissionId) {
            DB::table($permissionPivot)->insertOrIgnore([
                $permissionKey => $permissionId,
                $roleKey => $memberId,
            ]);
        }

        foreach (DB::table($pivotTable)->where($roleKey, $minimal->id)->get() as $assignment) {
            $row = (array) $assignment;
            $row[$roleKey] = $memberId;
            DB::table($pivotTable)->insertOrIgnore($row);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function drop(string $name, string $successor): void
    {
        $rolesTable = config('permission.table_names.roles');
        $pivotTable = config('permission.table_names.model_has_roles');
        $permissionPivot = config('permission.table_names.role_has_permissions');
        $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';

        $role = DB::table($rolesTable)->where('name', $name)->first();

        if (! $role) {
            return;
        }

        // Refuse rather than orphan people: every holder must already carry the
        // successor, or dropping this role silently takes their access away.
        $successorId = DB::table($rolesTable)->where('name', $successor)->value('id');

        if (! $successorId) {
            throw new RuntimeException("Cannot drop role '{$name}': '{$successor}' does not exist, so its holders would lose it.");
        }

        $orphans = DB::table($pivotTable.' as held')
            ->where('held.'.$roleKey, $role->id)
            ->whereNotExists(function ($query) use ($pivotTable, $roleKey, $successorId) {
                $query->select(DB::raw(1))
                    ->from($pivotTable.' as successor')
                    ->whereColumn('successor.model_id', 'held.model_id')
                    ->whereColumn('successor.model_type', 'held.model_type')
                    ->where('successor.'.$roleKey, $successorId);
            })
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException("Cannot drop role '{$name}': {$orphans} holder(s) do not have '{$successor}'.");
        }

        DB::table($pivotTable)->where($roleKey, $role->id)->delete();
        DB::table($permissionPivot)->where($roleKey, $role->id)->delete();
        DB::table($rolesTable)->where('id', $role->id)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
