<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_relaties', function (Blueprint $table) {
            $table->dropColumn('bsn');
        });
    }

    public function down(): void
    {
        Schema::table('soli_relaties', function (Blueprint $table) {
            $table->string('bsn')->nullable()->after('foto_url');
        });
    }
};
