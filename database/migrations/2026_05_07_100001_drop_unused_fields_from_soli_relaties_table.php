<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_relaties', function (Blueprint $table) {
            $table->dropColumn(['geslacht', 'foto_url', 'geboorteplaats', 'nationaliteit']);
        });
    }

    public function down(): void
    {
        Schema::table('soli_relaties', function (Blueprint $table) {
            $table->string('geslacht', 1)->default('O')->after('achternaam');
            $table->string('foto_url')->nullable()->after('beheerd_in_admin');
            $table->string('geboorteplaats')->nullable()->after('foto_url');
            $table->string('nationaliteit')->nullable()->after('geboorteplaats');
        });
    }
};
