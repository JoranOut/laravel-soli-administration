<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_relaties', function (Blueprint $table) {
            $table->boolean('beheerd_in_admin')->default(false)->after('actief');
        });
    }

    public function down(): void
    {
        Schema::table('soli_relaties', function (Blueprint $table) {
            $table->dropColumn('beheerd_in_admin');
        });
    }
};
