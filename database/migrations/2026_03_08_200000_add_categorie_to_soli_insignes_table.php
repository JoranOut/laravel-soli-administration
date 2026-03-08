<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_insignes', function (Blueprint $table) {
            $table->string('categorie')->nullable()->after('naam');
        });
    }

    public function down(): void
    {
        Schema::table('soli_insignes', function (Blueprint $table) {
            $table->dropColumn('categorie');
        });
    }
};
