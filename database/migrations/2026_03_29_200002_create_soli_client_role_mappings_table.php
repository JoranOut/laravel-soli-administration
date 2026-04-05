<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_client_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_setting_id')->constrained('soli_oauth_client_settings')->cascadeOnDelete();
            $table->foreignId('relatie_type_id')->constrained('soli_relatie_types')->cascadeOnDelete();
            $table->string('mapped_role', 100);
            $table->timestamps();

            $table->unique(['client_setting_id', 'relatie_type_id'], 'client_role_mapping_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_client_role_mappings');
    }
};
