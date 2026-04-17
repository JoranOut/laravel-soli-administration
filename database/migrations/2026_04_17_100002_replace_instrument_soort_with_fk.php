<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add nullable FK column
        Schema::table('soli_relatie_instrument', function (Blueprint $table) {
            $table->foreignId('instrument_soort_id')
                ->nullable()
                ->after('onderdeel_id')
                ->constrained('soli_instrument_soorten')
                ->cascadeOnDelete();
        });

        // 2. Insert any unknown existing instrument_soort values into soli_instrument_soorten
        $existingNames = DB::table('soli_instrument_soorten')->pluck('naam')->toArray();
        $unknownNames = DB::table('soli_relatie_instrument')
            ->select('instrument_soort')
            ->distinct()
            ->whereNotIn('instrument_soort', $existingNames)
            ->pluck('instrument_soort');

        $now = now();
        foreach ($unknownNames as $name) {
            DB::table('soli_instrument_soorten')->insert([
                'naam' => $name,
                'familie' => $name, // self-family fallback
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3. Populate instrument_soort_id by matching strings
        DB::statement('
            UPDATE soli_relatie_instrument ri
            INNER JOIN soli_instrument_soorten sis ON sis.naam = ri.instrument_soort
            SET ri.instrument_soort_id = sis.id
        ');

        // 4. Add new unique constraint first (this also serves as FK backing index for relatie_id)
        Schema::table('soli_relatie_instrument', function (Blueprint $table) {
            $table->unique(
                ['relatie_id', 'onderdeel_id', 'instrument_soort_id'],
                'soli_ri_relatie_onderdeel_soort_unique'
            );
        });

        // 5. Now safe to drop the old index (new unique covers relatie_id FK)
        Schema::table('soli_relatie_instrument', function (Blueprint $table) {
            $table->dropIndex('soli_relatie_instrument_relatie_id_onderdeel_id_index');
        });

        // 6. Drop old column and make FK non-nullable
        Schema::table('soli_relatie_instrument', function (Blueprint $table) {
            $table->dropColumn('instrument_soort');
        });

        Schema::table('soli_relatie_instrument', function (Blueprint $table) {
            $table->foreignId('instrument_soort_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('soli_relatie_instrument', function (Blueprint $table) {
            $table->dropUnique('soli_ri_relatie_onderdeel_soort_unique');
        });

        Schema::table('soli_relatie_instrument', function (Blueprint $table) {
            $table->string('instrument_soort')->after('onderdeel_id');
        });

        // Restore string values from FK
        DB::statement('
            UPDATE soli_relatie_instrument ri
            INNER JOIN soli_instrument_soorten sis ON sis.id = ri.instrument_soort_id
            SET ri.instrument_soort = sis.naam
        ');

        Schema::table('soli_relatie_instrument', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instrument_soort_id');
            $table->index(['relatie_id', 'onderdeel_id'], 'soli_relatie_instrument_relatie_id_onderdeel_id_index');
        });
    }
};
