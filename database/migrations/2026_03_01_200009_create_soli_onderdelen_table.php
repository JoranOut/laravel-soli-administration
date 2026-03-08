<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_onderdelen', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->enum('type', ['orkest', 'opleidingsgroep', 'ensemble', 'commissie', 'bestuur', 'staff', 'overig'])->default('orkest');
            $table->text('beschrijving')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_onderdelen');
    }
};
