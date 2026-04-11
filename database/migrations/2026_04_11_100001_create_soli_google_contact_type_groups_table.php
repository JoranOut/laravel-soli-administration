<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_google_contact_type_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatie_type_id')->constrained('soli_relatie_types')->cascadeOnDelete();
            $table->string('google_user_email');
            $table->string('google_resource_name');
            $table->timestamps();

            $table->unique(['relatie_type_id', 'google_user_email'], 'google_type_group_type_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_google_contact_type_groups');
    }
};
