<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAD records an instrument with a van and a tot, and until now the sync threw both
 * away: a member who switched from clarinet to sax kept both rows with nothing to
 * tell them apart. With periods, a pairing simply ends where SAD says it ends.
 *
 * Additive and nullable, so the previous release runs against this schema unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_relatie_instrument', function (Blueprint $table) {
            $table->date('van')->nullable()->after('instrument_soort_id');
            $table->date('tot')->nullable()->after('van');
        });
    }

    public function down(): void
    {
        Schema::table('soli_relatie_instrument', function (Blueprint $table) {
            $table->dropColumn(['van', 'tot']);
        });
    }
};
