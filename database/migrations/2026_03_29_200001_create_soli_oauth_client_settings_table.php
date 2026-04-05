<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_oauth_client_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_id')->unique();
            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('default_role', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_oauth_client_settings');
    }
};
