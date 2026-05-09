<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_relatie_onderdeel', function (Blueprint $table) {
            $table->boolean('beheerd_in_admin')->default(false)->after('tot');
        });
    }

    public function down(): void
    {
        Schema::table('soli_relatie_onderdeel', function (Blueprint $table) {
            $table->dropColumn('beheerd_in_admin');
        });
    }
};
