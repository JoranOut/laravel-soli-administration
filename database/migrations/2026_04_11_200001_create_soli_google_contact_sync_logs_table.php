<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_google_contact_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['full', 'relatie']);
            $table->foreignId('relatie_id')->nullable()->constrained('soli_relaties')->nullOnDelete();
            $table->enum('status', ['running', 'completed', 'failed']);
            $table->unsignedInteger('workspace_users')->default(0);
            $table->unsignedInteger('contacts_created')->default(0);
            $table->unsignedInteger('contacts_updated')->default(0);
            $table->unsignedInteger('contacts_deleted')->default(0);
            $table->unsignedInteger('contacts_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_google_contact_sync_logs');
    }
};
