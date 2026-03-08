<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_relatie_relatie_type', function (Blueprint $table) {
            $table->string('functie', 255)->nullable()->after('tot');
            $table->string('email', 255)->nullable()->after('functie');
        });
    }

    public function down(): void
    {
        Schema::table('soli_relatie_relatie_type', function (Blueprint $table) {
            $table->dropColumn(['functie', 'email']);
        });
    }
};
