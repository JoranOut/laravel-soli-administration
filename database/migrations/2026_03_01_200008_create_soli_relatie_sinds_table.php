<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_relatie_sinds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatie_id')->constrained('soli_relaties')->cascadeOnDelete();
            $table->date('lid_sinds');
            $table->date('lid_tot')->nullable();
            $table->string('reden_vertrek')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_relatie_sinds');
    }
};
