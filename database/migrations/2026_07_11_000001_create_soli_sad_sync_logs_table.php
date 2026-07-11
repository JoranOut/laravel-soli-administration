<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_sad_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['running', 'completed', 'completed_with_errors', 'failed']);
            $table->unsignedInteger('total')->nullable();
            $table->unsignedInteger('created')->nullable();
            $table->unsignedInteger('updated')->nullable();
            $table->unsignedInteger('skipped')->nullable();
            $table->unsignedInteger('failed')->nullable();
            $table->unsignedInteger('deactivated')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soli_sad_sync_logs');
    }
};
