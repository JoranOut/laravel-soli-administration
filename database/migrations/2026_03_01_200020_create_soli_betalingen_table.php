<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_betalingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('te_betalen_contributie_id')->constrained('soli_te_betalen_contributies')->cascadeOnDelete();
            $table->decimal('bedrag', 10, 2);
            $table->date('datum');
            $table->string('methode')->nullable();
            $table->text('opmerking')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_betalingen');
    }
};
