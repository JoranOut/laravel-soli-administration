<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh DB (migrate:fresh) — seeder handles everything
        if (DB::table('soli_instrument_families')->count() === 0) {
            return;
        }

        $now = now();

        // ──────────────────────────────────────────────
        // Step 1: Ensure all 16 target families exist
        // ──────────────────────────────────────────────
        $targetFamilies = [
            'Bas', 'Directiepartijen', 'Diverse', 'Dwarsfluit',
            'Fagot', 'Gitaar', 'Hobo', 'Hoorn', 'Klarinet',
            'Klein koper', 'Saxofoon', 'Slagwerk', 'Toetsen',
            'Trombone', 'Tuba', 'Zang',
        ];

        foreach ($targetFamilies as $naam) {
            $exists = DB::table('soli_instrument_families')
                ->where('naam', $naam)
                ->exists();

            if (! $exists) {
                DB::table('soli_instrument_families')->insert([
                    'naam' => $naam,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Build lookup: family name → id
        $familyIds = DB::table('soli_instrument_families')
            ->pluck('id', 'naam');

        // ──────────────────────────────────────────────
        // Step 2: Move instruments to correct families
        // Handles both seeder state and production state
        // ──────────────────────────────────────────────
        $instrumentFamilyMap = [
            // Klein koper (was Trompet)
            'Trompet' => 'Klein koper',
            'Cornet' => 'Klein koper',
            'Bugel' => 'Klein koper',

            // Tuba (merge: Bariton family + Althoorn from Hoorn + Tuba from Bas)
            'Bariton' => 'Tuba',
            'Euphonium' => 'Tuba',
            'Althoorn' => 'Tuba',
            'Tuba' => 'Tuba',

            // Toetsen (was Piano)
            'Keyboard' => 'Toetsen',
            'Piano' => 'Toetsen',

            // Directiepartijen (was Partituur / Tamboer-maître / Directie)
            'Partituur' => 'Directiepartijen',
            'Tamboer-maître' => 'Directiepartijen',
            'Dirigent' => 'Directiepartijen',

            // Diverse (was Majorette)
            'Majorette' => 'Diverse',
            'Vlaggenwacht' => 'Diverse',
        ];

        foreach ($instrumentFamilyMap as $instrumentNaam => $familieNaam) {
            DB::table('soli_instrument_soorten')
                ->where('naam', $instrumentNaam)
                ->update(['instrument_familie_id' => $familyIds[$familieNaam]]);
        }

        // ──────────────────────────────────────────────
        // Step 3: Add missing instruments
        // ──────────────────────────────────────────────
        $newInstruments = [
            ['naam' => 'Bas', 'familie' => 'Bas'],
            ['naam' => 'Buisklokken', 'familie' => 'Slagwerk'],
            ['naam' => 'Drumstel', 'familie' => 'Slagwerk'],
            ['naam' => 'Klokkenspel', 'familie' => 'Slagwerk'],
            ['naam' => 'Orgel', 'familie' => 'Toetsen'],
            ['naam' => 'Harp', 'familie' => 'Diverse'],
            ['naam' => 'Strijk', 'familie' => 'Diverse'],
            ['naam' => 'Dirigent', 'familie' => 'Directiepartijen'],
        ];

        foreach ($newInstruments as $instrument) {
            $exists = DB::table('soli_instrument_soorten')
                ->where('naam', $instrument['naam'])
                ->exists();

            if (! $exists) {
                DB::table('soli_instrument_soorten')->insert([
                    'naam' => $instrument['naam'],
                    'instrument_familie_id' => $familyIds[$instrument['familie']],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // ──────────────────────────────────────────────
        // Step 4: Remove Score (reassign references to Partituur)
        // ──────────────────────────────────────────────
        $score = DB::table('soli_instrument_soorten')
            ->where('naam', 'Score')
            ->first();

        if ($score) {
            $hasReferences = DB::table('soli_relatie_instrument')
                ->where('instrument_soort_id', $score->id)
                ->exists();

            if ($hasReferences) {
                $partituur = DB::table('soli_instrument_soorten')
                    ->where('naam', 'Partituur')
                    ->first();

                if ($partituur) {
                    DB::table('soli_relatie_instrument')
                        ->where('instrument_soort_id', $score->id)
                        ->update(['instrument_soort_id' => $partituur->id]);
                }
            }

            DB::table('soli_instrument_soorten')
                ->where('id', $score->id)
                ->delete();
        }

        // ──────────────────────────────────────────────
        // Step 5: Clean up old/empty families
        // ──────────────────────────────────────────────
        $obsoleteFamilies = [
            'Trompet', 'Piano', 'Bariton', 'Majorette',
            'Tamboer-maître', 'Partituur', 'Directie',
        ];

        foreach ($obsoleteFamilies as $naam) {
            $family = DB::table('soli_instrument_families')
                ->where('naam', $naam)
                ->first();

            if ($family) {
                $hasInstruments = DB::table('soli_instrument_soorten')
                    ->where('instrument_familie_id', $family->id)
                    ->exists();

                if (! $hasInstruments) {
                    DB::table('soli_instrument_families')
                        ->where('id', $family->id)
                        ->delete();
                }
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'This migration cannot be reversed automatically. Restore from backup if needed.'
        );
    }
};
