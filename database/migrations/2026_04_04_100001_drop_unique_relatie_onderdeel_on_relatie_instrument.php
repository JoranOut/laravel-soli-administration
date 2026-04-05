<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The unique index backs the relatie_id FK. Add a replacement index first,
        // then drop the unique, so the FK always has a backing index.
        DB::statement('ALTER TABLE soli_relatie_instrument ADD INDEX soli_relatie_instrument_relatie_id_onderdeel_id_index (relatie_id, onderdeel_id)');
        DB::statement('ALTER TABLE soli_relatie_instrument DROP INDEX soli_relatie_instrument_relatie_id_onderdeel_id_unique');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE soli_relatie_instrument ADD UNIQUE soli_relatie_instrument_relatie_id_onderdeel_id_unique (relatie_id, onderdeel_id)');
        DB::statement('ALTER TABLE soli_relatie_instrument DROP INDEX soli_relatie_instrument_relatie_id_onderdeel_id_index');
    }
};
