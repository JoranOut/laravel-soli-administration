<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_contributies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariefgroep_id')->constrained('soli_tariefgroepen')->cascadeOnDelete();
            $table->foreignId('soort_contributie_id')->constrained('soli_soort_contributies')->cascadeOnDelete();
            $table->year('jaar');
            $table->decimal('bedrag', 10, 2);
            $table->timestamps();

            $table->unique(['tariefgroep_id', 'soort_contributie_id', 'jaar'], 'soli_contributies_tarief_soort_jaar_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_contributies');
    }
};
