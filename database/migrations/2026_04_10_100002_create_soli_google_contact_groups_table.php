<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_google_contact_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onderdeel_id')->constrained('soli_onderdelen')->cascadeOnDelete();
            $table->string('google_user_email');
            $table->string('google_resource_name');
            $table->timestamps();

            $table->unique(['onderdeel_id', 'google_user_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_google_contact_groups');
    }
};
