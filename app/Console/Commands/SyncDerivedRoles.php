<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DerivedRoleSyncService;
use Illuminate\Console\Command;

class SyncDerivedRoles extends Command
{
    protected $signature = 'roles:sync-derived
        {--dry-run : Show what would change without touching any role}';

    protected $description = 'Sync internal roles that are derived from relatie types';

    public function handle(DerivedRoleSyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $managed = $service->managedRoleNames();

        if ($managed === []) {
            $this->warn('No relatie type is mapped to a role, so there is nothing to sync.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY RUN] No roles will be changed.');
        }

        $this->line('Managed roles: '.implode(', ', $managed));

        $changed = $service->syncAll(function (User $user, array $result) {
            $parts = [];

            if ($result['added'] !== []) {
                $parts[] = '+'.implode(' +', $result['added']);
            }

            if ($result['removed'] !== []) {
                $parts[] = '-'.implode(' -', $result['removed']);
            }

            $this->line("  {$user->email}: ".implode(' ', $parts));
        }, $dryRun);

        $verb = $dryRun ? 'would change' : 'changed';
        $this->info("{$changed} user(s) {$verb}.");

        return self::SUCCESS;
    }
}
