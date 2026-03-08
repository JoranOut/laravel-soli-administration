<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_diplomas', function (Blueprint $table) {
            $table->dropColumn('datum');
        });
    }

    public function down(): void
    {
        Schema::table('soli_diplomas', function (Blueprint $table) {
            $table->date('datum')->after('instrument');
        });
    }
};
