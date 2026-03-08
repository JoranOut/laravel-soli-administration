<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_relaties', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('relatie_nummer')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('voornaam');
            $table->string('tussenvoegsel')->nullable();
            $table->string('achternaam');
            $table->enum('geslacht', ['M', 'V', 'O'])->default('O');
            $table->date('geboortedatum')->nullable();
            $table->boolean('actief')->default(true);
            $table->string('foto_url')->nullable();
            $table->text('bsn')->nullable();
            $table->string('geboorteplaats')->nullable();
            $table->string('nationaliteit')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_relaties');
    }
};
