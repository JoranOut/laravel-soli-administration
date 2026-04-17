<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_oauth_client_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_setting_id')->constrained('soli_oauth_client_settings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mapped_role', 100);
            $table->timestamps();

            $table->unique(['client_setting_id', 'user_id'], 'client_user_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_oauth_client_user_roles');
    }
};
