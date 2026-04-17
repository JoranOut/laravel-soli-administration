<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_instrument_soorten', function (Blueprint $table) {
            $table->id();
            $table->string('naam')->unique();
            $table->string('familie');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_instrument_soorten');
    }
};
