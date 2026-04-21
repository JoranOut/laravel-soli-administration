<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soli_instrument_families', function (Blueprint $table) {
            $table->id();
            $table->string('naam')->unique();
            $table->timestamps();
        });

        // Extract distinct familie values into the new table
        $families = DB::table('soli_instrument_soorten')
            ->select('familie')
            ->distinct()
            ->whereNotNull('familie')
            ->pluck('familie');

        $now = now();
        foreach ($families as $naam) {
            DB::table('soli_instrument_families')->insert([
                'naam' => $naam,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Add FK column
        Schema::table('soli_instrument_soorten', function (Blueprint $table) {
            $table->foreignId('instrument_familie_id')
                ->nullable()
                ->after('naam')
                ->constrained('soli_instrument_families');
        });

        // Populate FK from existing string values
        $familyMap = DB::table('soli_instrument_families')->pluck('id', 'naam');
        foreach ($familyMap as $naam => $id) {
            DB::table('soli_instrument_soorten')
                ->where('familie', $naam)
                ->update(['instrument_familie_id' => $id]);
        }

        // Drop old string column
        Schema::table('soli_instrument_soorten', function (Blueprint $table) {
            $table->dropColumn('familie');
        });
    }

    public function down(): void
    {
        Schema::table('soli_instrument_soorten', function (Blueprint $table) {
            $table->string('familie')->nullable()->after('naam');
        });

        // Restore string values from FK
        $families = DB::table('soli_instrument_families')->pluck('naam', 'id');
        foreach ($families as $id => $naam) {
            DB::table('soli_instrument_soorten')
                ->where('instrument_familie_id', $id)
                ->update(['familie' => $naam]);
        }

        Schema::table('soli_instrument_soorten', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instrument_familie_id');
        });

        Schema::dropIfExists('soli_instrument_families');
    }
};
