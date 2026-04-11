<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_relatie_types', function (Blueprint $table) {
            $table->boolean('onderdeel_koppelbaar')->default(false)->after('naam');
        });

        DB::table('soli_relatie_types')
            ->whereIn('naam', ['dirigent', 'contactpersoon'])
            ->update(['onderdeel_koppelbaar' => true]);
    }

    public function down(): void
    {
        Schema::table('soli_relatie_types', function (Blueprint $table) {
            $table->dropColumn('onderdeel_koppelbaar');
        });
    }
};
