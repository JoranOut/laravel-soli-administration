<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_adressen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatie_id')->constrained('soli_relaties')->cascadeOnDelete();
            $table->string('straat');
            $table->string('huisnummer');
            $table->string('huisnummer_toevoeging')->nullable();
            $table->string('postcode');
            $table->string('plaats');
            $table->string('land')->default('Nederland');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_adressen');
    }
};
