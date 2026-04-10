<?php

namespace App\Console\Commands;

use App\Jobs\SyncGoogleContactsJob;
use App\Models\Relatie;
use App\Services\Google\GoogleContactSyncService;
use Illuminate\Console\Command;

class SyncGoogleContacts extends Command
{
    protected $signature = 'sync:google-contacts
        {--relatie= : Sync a specific relatie only}
        {--sync : Run synchronously (not queued)}
        {--dry-run : Show what would change without calling API}';

    protected $description = 'Sync Soli members to Google Contacts for all Workspace users';

    public function handle(GoogleContactSyncService $syncService): int
    {
        if (! config('services.google.contacts_sync_enabled')) {
            $this->error('Google Contacts sync is disabled. Set GOOGLE_CONTACTS_SYNC_ENABLED=true in .env');

            return self::FAILURE;
        }

        $relatieId = $this->option('relatie');
        $synchronous = $this->option('sync');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('[DRY RUN] Showing what would change without calling Google API...');
        }

        if ($dryRun || $synchronous) {
            return $this->runDirectly($syncService, $relatieId, $dryRun);
        }

        // Dispatch to queue
        SyncGoogleContactsJob::dispatch($relatieId ? (int) $relatieId : null);
        $this->info($relatieId
            ? "Queued Google Contacts sync for relatie #{$relatieId}."
            : 'Queued full Google Contacts sync for all relaties.');

        return self::SUCCESS;
    }

    private function runDirectly(GoogleContactSyncService $syncService, ?string $relatieId, bool $dryRun): int
    {
        if ($relatieId) {
            $relatie = Relatie::with(['emails', 'onderdelen'])->find($relatieId);

            if (! $relatie) {
                $this->error("Relatie #{$relatieId} not found.");

                return self::FAILURE;
            }

            $this->info("Syncing relatie #{$relatieId} ({$relatie->volledige_naam})...");
            $summary = $syncService->syncRelatie($relatie, $dryRun);
        } else {
            $this->info('Syncing all active relaties...');
            $summary = $syncService->syncAll($dryRun);
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Workspace users', $summary['users']],
                ['Contacts created', $summary['created']],
                ['Contacts updated', $summary['updated']],
                ['Contacts deleted', $summary['deleted']],
                ['Contacts skipped (no changes)', $summary['skipped']],
            ],
        );

        $this->info($dryRun ? 'Dry run complete.' : 'Sync complete.');

        return self::SUCCESS;
    }
}
