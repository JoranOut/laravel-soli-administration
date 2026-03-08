<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_insignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatie_id')->constrained('soli_relaties')->cascadeOnDelete();
            $table->string('naam');
            $table->date('datum');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_insignes');
    }
};
