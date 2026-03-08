<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_instrument_bijzonderheden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained('soli_instrumenten')->cascadeOnDelete();
            $table->text('beschrijving');
            $table->date('datum');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_instrument_bijzonderheden');
    }
};
