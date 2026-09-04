<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Rename the member role to minimal.
     *
     * A rename, not a drop: the row keeps its id, so every existing
     * model_has_roles assignment survives untouched. Fully reversible.
     */
    public function up(): void
    {
        $this->rename('member', 'minimal');
    }

    public function down(): void
    {
        $this->rename('minimal', 'member');
    }

    private function rename(string $from, string $to): void
    {
        $table = config('permission.table_names.roles');

        // Refuse rather than merge if both names somehow exist
        if (DB::table($table)->where('name', $to)->exists()) {
            throw new RuntimeException("Cannot rename role '{$from}': a role named '{$to}' already exists.");
        }

        DB::table($table)->where('name', $from)->update(['name' => $to]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
