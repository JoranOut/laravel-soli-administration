<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_relatie_relatie_type', function (Blueprint $table) {
            $table->foreignId('onderdeel_id')
                ->nullable()
                ->after('email')
                ->constrained('soli_onderdelen')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('soli_relatie_relatie_type', function (Blueprint $table) {
            $table->dropConstrainedForeignId('onderdeel_id');
        });
    }
};
