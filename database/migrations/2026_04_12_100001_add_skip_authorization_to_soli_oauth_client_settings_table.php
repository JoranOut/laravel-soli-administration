<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_oauth_client_settings', function (Blueprint $table) {
            $table->boolean('skip_authorization')->default(false)->after('default_role');
        });
    }

    public function down(): void
    {
        Schema::table('soli_oauth_client_settings', function (Blueprint $table) {
            $table->dropColumn('skip_authorization');
        });
    }
};
