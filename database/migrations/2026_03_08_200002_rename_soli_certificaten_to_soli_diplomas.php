<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('soli_certificaten', 'soli_diplomas');
    }

    public function down(): void
    {
        Schema::rename('soli_diplomas', 'soli_certificaten');
    }
};
