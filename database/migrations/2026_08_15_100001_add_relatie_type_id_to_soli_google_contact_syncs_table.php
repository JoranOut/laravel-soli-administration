<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soli_google_contact_syncs', function (Blueprint $table) {
            // null = main contact; set = split contact for a type with a functional email
            $table->foreignId('relatie_type_id')
                ->nullable()
                ->after('relatie_id')
                ->constrained('soli_relatie_types')
                ->cascadeOnDelete();

            // The new unique index starts with relatie_id, so it can serve the
            // relatie_id foreign key once the old unique index is dropped.
            $table->unique(
                ['relatie_id', 'relatie_type_id', 'google_user_email'],
                'soli_gcs_relatie_type_user_unique'
            );
            $table->dropUnique(['relatie_id', 'google_user_email']);
        });
    }

    public function down(): void
    {
        // Split-contact rows would collide on the restored (relatie_id, google_user_email) unique
        DB::table('soli_google_contact_syncs')->whereNotNull('relatie_type_id')->delete();

        Schema::table('soli_google_contact_syncs', function (Blueprint $table) {
            $table->unique(['relatie_id', 'google_user_email']);
            $table->dropUnique('soli_gcs_relatie_type_user_unique');
            $table->dropConstrainedForeignId('relatie_type_id');
        });
    }
};
