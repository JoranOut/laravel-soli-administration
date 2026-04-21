<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add muziekgroep to enum so the value is accepted
        DB::statement("ALTER TABLE soli_onderdelen MODIFY COLUMN type ENUM('orkest', 'opleidingsgroep', 'ensemble', 'muziekgroep', 'commissie', 'bestuur', 'staff', 'overig') NOT NULL");

        // 2. Update existing rows
        DB::table('soli_onderdelen')
            ->whereIn('type', ['orkest', 'ensemble', 'opleidingsgroep'])
            ->update(['type' => 'muziekgroep']);

        // 3. Shrink enum to final set
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
