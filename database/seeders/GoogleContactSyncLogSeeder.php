<?php

namespace Database\Seeders;

use App\Models\GoogleContactSyncLog;
use App\Models\Relatie;
use Illuminate\Database\Seeder;

class GoogleContactSyncLogSeeder extends Seeder
{
    public function run(): void
    {
        $relatie = Relatie::first();

        // Successful full sync
        GoogleContactSyncLog::create([
            'type' => 'full',
            'status' => 'completed',
            'workspace_users' => 3,
            'contacts_created' => 42,
            'contacts_updated' => 8,
            'contacts_deleted' => 2,
            'contacts_skipped' => 300,
            'started_at' => now()->subDays(3)->setTime(2, 0),
            'completed_at' => now()->subDays(3)->setTime(2, 2, 47),
        ]);

        // Successful single relatie sync
        if ($relatie) {
            GoogleContactSyncLog::create([
                'type' => 'relatie',
                'relatie_id' => $relatie->id,
                'status' => 'completed',
                'workspace_users' => 3,
                'contacts_created' => 0,
                'contacts_updated' => 3,
                'contacts_deleted' => 0,
                'contacts_skipped' => 0,
                'started_at' => now()->subDays(2)->setTime(14, 30),
                'completed_at' => now()->subDays(2)->setTime(14, 30, 8),
            ]);
        }

        // Failed full sync — Google API error
        GoogleContactSyncLog::create([
            'type' => 'full',
            'status' => 'failed',
            'workspace_users' => 0,
            'started_at' => now()->subDays(1)->setTime(2, 0),
            'completed_at' => now()->subDays(1)->setTime(2, 0, 16),
            'error_message' => "Error calling POST https://people.googleapis.com/v1/people:batchCreateContacts: {\n  \"error\": {\n    \"code\": 502,\n    \"message\": \"Bad Gateway\",\n    \"status\": \"UNAVAILABLE\",\n    \"details\": [\n      {\n        \"@type\": \"type.googleapis.com/google.rpc.DebugInfo\",\n        \"detail\": \"backend connection error\"\n      }\n    ]\n  }\n}",
        ]);

        // Running full sync (simulates an in-progress sync)
        GoogleContactSyncLog::create([
            'type' => 'full',
            'status' => 'running',
            'workspace_users' => 0,
            'started_at' => now()->subMinutes(2),
        ]);

        // Another successful full sync
        GoogleContactSyncLog::create([
            'type' => 'full',
            'status' => 'completed',
            'workspace_users' => 3,
            'contacts_created' => 0,
            'contacts_updated' => 0,
            'contacts_deleted' => 0,
            'contacts_skipped' => 352,
            'started_at' => now()->subHours(6),
            'completed_at' => now()->subHours(6)->addSeconds(94),
        ]);

        // Failed sync — permission error
        GoogleContactSyncLog::create([
            'type' => 'full',
            'status' => 'failed',
            'workspace_users' => 0,
            'started_at' => now()->subDays(5)->setTime(2, 0),
            'completed_at' => now()->subDays(5)->setTime(2, 0, 3),
            'error_message' => "file_get_contents(/var/www/admin.soli.nl/shared/google-service-account.json): Failed to open stream: Permission denied",
        ]);
    }
}
