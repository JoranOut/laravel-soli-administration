<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Expand step of renaming `member` to `minimal`.
     *
     * Migrations run before the release swap, so for a few minutes the
     * *previous* release runs against this schema. That release still calls
     * hasRole('member'), and Spatie answers false rather than throwing, which
     * would have let any member list every relatie. So `member` is left intact
     * here and its assignments are copied onto a new `minimal` role; both exist
     * for one release. The contract step — dropping `member` — belongs in the
     * next deploy, once this one has proven healthy.
     */
    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles');
        $pivotTable = config('permission.table_names.model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';

        $member = DB::table($rolesTable)->where('name', 'member')->first();

        if (! $member) {
            // Fresh database: RolesAndPermissionsSeeder creates `minimal` itself.
            return;
        }

        $minimalId = DB::table($rolesTable)->where('name', 'minimal')->value('id');

        if (! $minimalId) {
            $minimalId = DB::table($rolesTable)->insertGetId([
                'name' => 'minimal',
                'guard_name' => $member->guard_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Same permissions, so the new role behaves like the old one
        $permissionPivot = config('permission.table_names.role_has_permissions');
        $permissionKey = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

        $permissionIds = DB::table($permissionPivot)
            ->where($roleKey, $member->id)
            ->pluck($permissionKey);

        foreach ($permissionIds as $permissionId) {
            DB::table($permissionPivot)->insertOrIgnore([
                $permissionKey => $permissionId,
                $roleKey => $minimalId,
            ]);
        }

        // Copy every assignment, keeping the member rows in place
        $assignments = DB::table($pivotTable)->where($roleKey, $member->id)->get();

        foreach ($assignments as $assignment) {
            $row = (array) $assignment;
            $row[$roleKey] = $minimalId;
            DB::table($pivotTable)->insertOrIgnore($row);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Removes the added role and its assignments. The member rows were never
     * touched, so the previous state is fully restored.
     */
    public function down(): void
    {
        $rolesTable = config('permission.table_names.roles');
        $pivotTable = config('permission.table_names.model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';

        $minimalId = DB::table($rolesTable)->where('name', 'minimal')->value('id');

        if (! $minimalId || ! DB::table($rolesTable)->where('name', 'member')->exists()) {
            return;
        }

        DB::table($pivotTable)->where($roleKey, $minimalId)->delete();
        DB::table($rolesTable)->where('id', $minimalId)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
