<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_instrumenten', function (Blueprint $table) {
            $table->id();
            $table->string('nummer')->unique();
            $table->string('soort');
            $table->string('merk')->nullable();
            $table->string('model')->nullable();
            $table->string('serienummer')->nullable();
            $table->enum('status', ['beschikbaar', 'in_gebruik', 'in_reparatie', 'afgeschreven'])->default('beschikbaar');
            $table->enum('eigendom', ['soli', 'bruikleen', 'eigen'])->default('soli');
            $table->year('aanschafjaar')->nullable();
            $table->decimal('prijs', 10, 2)->nullable();
            $table->string('locatie')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_instrumenten');
    }
};
