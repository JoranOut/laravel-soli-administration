<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_relatie_instrument', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatie_id')->constrained('soli_relaties')->cascadeOnDelete();
            $table->foreignId('onderdeel_id')->constrained('soli_onderdelen')->cascadeOnDelete();
            $table->string('instrument_soort');
            $table->timestamps();

            $table->unique(['relatie_id', 'onderdeel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_relatie_instrument');
    }
};
