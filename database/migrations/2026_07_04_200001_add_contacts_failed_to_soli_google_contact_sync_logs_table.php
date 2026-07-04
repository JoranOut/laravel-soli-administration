<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_google_contact_sync_logs', function (Blueprint $table) {
            $table->unsignedInteger('contacts_failed')->default(0)->after('contacts_skipped');
        });

        DB::statement("ALTER TABLE soli_google_contact_sync_logs MODIFY COLUMN status ENUM('running', 'completed', 'completed_with_errors', 'failed')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE soli_google_contact_sync_logs MODIFY COLUMN status ENUM('running', 'completed', 'failed')");

        Schema::table('soli_google_contact_sync_logs', function (Blueprint $table) {
            $table->dropColumn('contacts_failed');
        });
    }
};
