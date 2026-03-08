<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_te_betalen_contributies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatie_id')->constrained('soli_relaties')->cascadeOnDelete();
            $table->foreignId('contributie_id')->constrained('soli_contributies')->cascadeOnDelete();
            $table->year('jaar');
            $table->decimal('bedrag', 10, 2);
            $table->enum('status', ['open', 'betaald', 'kwijtgescholden'])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_te_betalen_contributies');
    }
};
