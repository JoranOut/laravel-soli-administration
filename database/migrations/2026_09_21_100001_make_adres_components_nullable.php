<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAD delivers address components separately and often incompletely — a member may
 * have a postcode and plaats but no straat, or the reverse. The table was designed
 * for the relatie wizard, where StoreRelatieRequest requires all four, so both SAD
 * paths hit "Column 'postcode' cannot be null" and lost the whole address.
 *
 * Relaxing NOT NULL is backwards compatible: the wizard keeps its own validation,
 * so the previous release still writes complete addresses against this schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_adressen', function (Blueprint $table) {
            $table->string('straat')->nullable()->change();
            $table->string('huisnummer')->nullable()->change();
            $table->string('postcode')->nullable()->change();
            $table->string('plaats')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows added in the meantime may hold nulls, which would make the column
        // definitions unrestorable. Blank them first so the constraint can return.
        foreach (['straat', 'huisnummer', 'postcode', 'plaats'] as $column) {
            DB::table('soli_adressen')->whereNull($column)->update([$column => '']);
        }

        Schema::table('soli_adressen', function (Blueprint $table) {
            $table->string('straat')->nullable(false)->change();
            $table->string('huisnummer')->nullable(false)->change();
            $table->string('postcode')->nullable(false)->change();
            $table->string('plaats')->nullable(false)->change();
        });
    }
};
