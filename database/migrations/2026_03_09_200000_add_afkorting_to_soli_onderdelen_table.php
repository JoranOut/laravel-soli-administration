<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_onderdelen', function (Blueprint $table) {
            $table->string('afkorting', 10)->nullable()->unique()->after('naam');
        });
    }

    public function down(): void
    {
        Schema::table('soli_onderdelen', function (Blueprint $table) {
            $table->dropColumn('afkorting');
        });
    }
};
