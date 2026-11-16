<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activitylog v5 stores model changes in `attribute_changes` instead of `properties`,
 * and no longer has batches.
 */
return new class extends Migration
{
    private const TABLE = 'soli_activity_log';

    private const CHANGE_KEYS = ['attributes', 'old'];

    public function up(): void
    {
        if (DB::table(self::TABLE)->whereNotNull('batch_uuid')->exists()) {
            throw new RuntimeException('soli_activity_log has rows with a batch_uuid; dropping the column would lose them.');
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->json('attribute_changes')->nullable()->after('causer_id');
        });

        DB::transaction(function () {
            // Only model events carry tracked changes. Manual activity() logs have no event
            // and may use `old` as their own key (UserRoleController logs old/new roles).
            DB::table(self::TABLE)
                ->whereNotNull('event')
                ->whereNotNull('properties')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        $properties = json_decode($row->properties, true);

                        if (! is_array($properties)) {
                            continue;
                        }

                        $changes = array_intersect_key($properties, array_flip(self::CHANGE_KEYS));

                        if ($changes === []) {
                            continue;
                        }

                        $remaining = array_diff_key($properties, $changes);

                        DB::table(self::TABLE)->where('id', $row->id)->update([
                            'attribute_changes' => json_encode($changes),
                            'properties' => $remaining === [] ? null : json_encode($remaining),
                        ]);
                    }
                });
        });

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn('batch_uuid');
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('properties');
        });

        DB::transaction(function () {
            DB::table(self::TABLE)
                ->whereNotNull('attribute_changes')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        $changes = json_decode($row->attribute_changes, true) ?? [];
                        $properties = json_decode($row->properties ?? 'null', true) ?? [];

                        DB::table(self::TABLE)->where('id', $row->id)->update([
                            'properties' => json_encode(array_merge($properties, $changes)),
                        ]);
                    }
                });
        });

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn('attribute_changes');
        });
    }
};
