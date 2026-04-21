<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update existing rows
        DB::table('soli_onderdelen')
            ->whereIn('type', ['orkest', 'ensemble', 'opleidingsgroep'])
            ->update(['type' => 'muziekgroep']);

        // 2. Alter enum column
        DB::statement("ALTER TABLE soli_onderdelen MODIFY COLUMN type ENUM('muziekgroep', 'commissie', 'bestuur', 'staff', 'overig') NOT NULL");
    }

    public function down(): void
    {
        // 1. Alter enum column back (add old values)
        DB::statement("ALTER TABLE soli_onderdelen MODIFY COLUMN type ENUM('orkest', 'opleidingsgroep', 'ensemble', 'muziekgroep', 'commissie', 'bestuur', 'staff', 'overig') NOT NULL");

        // 2. Default back to orkest since we can't know the original
        DB::table('soli_onderdelen')
            ->where('type', 'muziekgroep')
            ->update(['type' => 'orkest']);

        // 3. Remove muziekgroep from enum
        DB::statement("ALTER TABLE soli_onderdelen MODIFY COLUMN type ENUM('orkest', 'opleidingsgroep', 'ensemble', 'commissie', 'bestuur', 'staff', 'overig') NOT NULL");
    }
};
