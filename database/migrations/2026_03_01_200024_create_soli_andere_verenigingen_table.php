<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_andere_verenigingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatie_id')->constrained('soli_relaties')->cascadeOnDelete();
            $table->string('naam');
            $table->string('functie')->nullable();
            $table->date('van');
            $table->date('tot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_andere_verenigingen');
    }
};
