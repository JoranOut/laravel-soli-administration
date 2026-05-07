<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_instrument_families', function (Blueprint $table) {
            $table->unsignedBigInteger('external_id')->nullable()->unique()->after('id');
        });

        Schema::table('soli_instrument_soorten', function (Blueprint $table) {
            $table->unsignedBigInteger('external_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('soli_instrument_soorten', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn('external_id');
        });

        Schema::table('soli_instrument_families', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};
