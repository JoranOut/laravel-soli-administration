<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_relatie_type_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatie_type_id')->constrained('soli_relatie_types')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained(config('permission.table_names.roles'))->cascadeOnDelete();
            $table->timestamps();

            // One role per relatie type. Several types may feed the same role
            // (lid, donateur and vrijwilliger all map to minimal).
            $table->unique('relatie_type_id', 'relatie_type_role_mapping_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_relatie_type_role_mappings');
    }
};
