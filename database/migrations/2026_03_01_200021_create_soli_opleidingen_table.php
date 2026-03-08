<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_opleidingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatie_id')->constrained('soli_relaties')->cascadeOnDelete();
            $table->string('naam');
            $table->string('instituut')->nullable();
            $table->string('instrument')->nullable();
            $table->string('diploma')->nullable();
            $table->date('datum_start')->nullable();
            $table->date('datum_einde')->nullable();
            $table->text('opmerking')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_opleidingen');
    }
};
