<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_client_role_mappings', function (Blueprint $table) {
            $table->unsignedInteger('priority')->default(0)->after('mapped_role');
        });
    }

    public function down(): void
    {
        Schema::table('soli_client_role_mappings', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
