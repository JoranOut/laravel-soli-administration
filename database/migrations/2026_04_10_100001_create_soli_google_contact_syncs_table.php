<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_google_contact_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatie_id')->constrained('soli_relaties')->cascadeOnDelete();
            $table->string('google_user_email');
            $table->string('google_resource_name');
            $table->string('data_hash', 64);
            $table->timestamps();

            $table->unique(['relatie_id', 'google_user_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_google_contact_syncs');
    }
};
