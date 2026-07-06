<?php

namespace App\Console\Commands;

use App\Services\Sad\SadSyncService;
use Illuminate\Console\Command;

class SyncSadMembers extends Command
{
    protected $signature = 'sync:sad-members';

    protected $description = 'Sync members from SAD system including PII data';

    public function handle(SadSyncService $syncService): int
    {
        $this->info('Starting SAD member sync...');

        try {
            $stats = $syncService->syncAll();
        } catch (\Throwable $e) {
            $this->error("Sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total members', $stats['total']],
                ['Created', $stats['created']],
                ['Updated', $stats['updated']],
                ['Skipped (admin-managed)', $stats['skipped']],
                ['Failed', $stats['failed']],
                ['Deactivated', $stats['deactivated']],
            ],
        );

        if (! empty($stats['warnings'])) {
            $this->warn('Warnings:');
            foreach ($stats['warnings'] as $warning) {
                $this->line("  - {$warning}");
            }
        }

        $this->info('Sync complete.');

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
