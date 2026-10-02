<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The unique on (relatie_id, onderdeel_id, instrument_soort_id) dates from April,
 * when a row simply said "this member plays this in that onderdeel" and carried no
 * dates. Now that rows are periods, the same combination legitimately recurs: a
 * trumpet in the harmonie from 2006 to 2012, and again from 2015.
 *
 * It cost 112 of 206 members on the first real sync — each one died on a duplicate
 * key and was counted as failed.
 *
 * The replacement includes van, so a true duplicate is still refused. Note MySQL
 * permits repeated NULLs in a unique index, so rows without a start date are not
 * constrained by it; the sync de-duplicates those itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The unique also backs the relatie_id foreign key, so put a replacement
        // index in place before dropping it — same order as the April migration.
        DB::statement('ALTER TABLE soli_relatie_instrument ADD INDEX soli_ri_relatie_onderdeel_index (relatie_id, onderdeel_id)');
        DB::statement('ALTER TABLE soli_relatie_instrument DROP INDEX soli_ri_relatie_onderdeel_soort_unique');
        DB::statement('ALTER TABLE soli_relatie_instrument ADD UNIQUE soli_ri_relatie_onderdeel_soort_van_unique (relatie_id, onderdeel_id, instrument_soort_id, van)');
    }

    public function down(): void
    {
        // Rows that only differ by van would violate the old unique, so the periods
        // have to go before it can return. Keep the earliest of each combination.
        DB::statement('
            DELETE ri FROM soli_relatie_instrument ri
            JOIN soli_relatie_instrument keep
              ON keep.relatie_id = ri.relatie_id
             AND keep.onderdeel_id = ri.onderdeel_id
             AND keep.instrument_soort_id = ri.instrument_soort_id
             AND keep.id < ri.id
        ');

        DB::statement('ALTER TABLE soli_relatie_instrument DROP INDEX soli_ri_relatie_onderdeel_soort_van_unique');
        DB::statement('ALTER TABLE soli_relatie_instrument ADD UNIQUE soli_ri_relatie_onderdeel_soort_unique (relatie_id, onderdeel_id, instrument_soort_id)');
        DB::statement('ALTER TABLE soli_relatie_instrument DROP INDEX soli_ri_relatie_onderdeel_index');
    }
};
