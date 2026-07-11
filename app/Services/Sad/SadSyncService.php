<?php

namespace App\Services\Sad;

use App\Models\JobStatus;
use App\Models\SadSyncLog;
use App\Services\MemberSyncService;
use Illuminate\Support\Facades\Log;

class SadSyncService
{
    public function __construct(
        private SadApiClient $apiClient,
        private MemberSyncService $memberSyncService,
    ) {}

    /**
     * Sync all members from SAD, including PII data.
     *
     * @return array{total: int, created: int, updated: int, skipped: int, failed: int, deactivated: int, warnings: string[]}
     */
    public function syncAll(): array
    {
        $jobStatus = JobStatus::markRunning('sad-sync', 'SAD Member Sync');
        $log = SadSyncLog::create(['status' => 'running', 'started_at' => now()]);

        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'deactivated' => 0,
            'warnings' => [],
        ];

        try {
            // Step 1: Login to SAD
            $this->apiClient->login();

            // Step 2: Fetch active member list
            $members = $this->apiClient->getActiveMembers();
            $stats['total'] = count($members);

            Log::info("SadSyncService: Found {$stats['total']} active members");

            // Step 3: For each member, fetch details + PII and upsert
            foreach ($members as $lidId => $member) {
                try {
                    $this->syncMember($lidId, $member, $stats);
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    Log::warning("SadSyncService: Failed to sync lid_id {$lidId}: {$e->getMessage()}");
                }
            }

            // Step 4: Reconcile — deactivate members no longer in SAD
            try {
                $activeLidIds = array_map('intval', array_keys($members));
                $reconcileResult = $this->memberSyncService->reconcileMembers($activeLidIds);
                $stats['deactivated'] = $reconcileResult['deactivated_count'];
            } catch (\Throwable $e) {
                Log::warning("SadSyncService: Reconcile failed: {$e->getMessage()}");
                $stats['warnings'][] = "Reconcile failed: {$e->getMessage()}";
            }

            // Step 5: Update job status
            $hasWarnings = ! empty($stats['warnings']) || $stats['failed'] > 0;
            $metadata = array_diff_key($stats, ['warnings' => true]);

            if ($hasWarnings) {
                $errorSummary = $stats['failed'] > 0
                    ? "{$stats['failed']} members failed to sync"
                    : implode('; ', array_slice($stats['warnings'], 0, 3));
                $jobStatus->markCompletedWithErrors($errorSummary, $metadata);
                $log->update(array_merge(['status' => 'completed_with_errors', 'completed_at' => now(), 'error_message' => $errorSummary], $metadata));
            } else {
                $jobStatus->markCompleted($metadata);
                $log->update(array_merge(['status' => 'completed', 'completed_at' => now()], $metadata));
            }

            Log::info('SadSyncService: Sync completed', $metadata);

        } catch (\Throwable $e) {
            $jobStatus->markFailed($e->getMessage());
            $log->update(['status' => 'failed', 'completed_at' => now(), 'error_message' => $e->getMessage()]);
            Log::error("SadSyncService: Sync failed: {$e->getMessage()}");

            throw $e;
        }

        return $stats;
    }

    private function syncMember(int $lidId, array $member, array &$stats): void
    {
        // Fetch detailed member info
        $details = $this->apiClient->getMemberDetails($lidId);
        if (! $details) {
            $stats['failed']++;

            return;
        }

        // Fetch PII (authenticated)
        $pii = $this->apiClient->getMemberPii($lidId);

        // Split onderdeel codes (e.g. "HABB" → ["HA", "BB"])
        $onderdeelCodes = $this->splitOnderdeelCodes($member['onderdeel']);

        // Build the data array for MemberSyncService
        $data = [
            'voornaam' => $details['voornaam'],
            'tussenvoegsel' => $details['tussenvoegsel'],
            'achternaam' => $details['achternaam'],
            'email' => $details['email'],
            'onderdeel_codes' => $onderdeelCodes,
        ];

        // Merge PII fields if available
        if ($pii) {
            if ($pii['geboortedatum'] !== null) {
                $data['geboortedatum'] = $pii['geboortedatum'];
            }
            if ($pii['adres'] !== null) {
                $data['adres'] = $pii['adres'];
            }
            if ($pii['postcode'] !== null) {
                $data['postcode'] = $pii['postcode'];
            }
            if ($pii['plaats'] !== null) {
                $data['plaats'] = $pii['plaats'];
            }
            if ($pii['telefoon'] !== null) {
                $data['telefoon'] = $pii['telefoon'];
            }
            if ($pii['instrument'] !== null) {
                $data['instrument'] = $pii['instrument'];
            }
        }

        // Upsert member
        $result = $this->memberSyncService->upsertMember($lidId, $data);

        match ($result['status']) {
            MemberSyncService::STATUS_CREATED => $stats['created']++,
            MemberSyncService::STATUS_UPDATED => $stats['updated']++,
            MemberSyncService::STATUS_SKIPPED => $stats['skipped']++,
            default => null,
        };

        if (! empty($result['warnings'])) {
            foreach ($result['warnings'] as $warning) {
                $stats['warnings'][] = "lid_id {$lidId}: {$warning}";
            }
        }
    }

    /**
     * Split a combined onderdeel string into 2-character codes.
     *
     * E.g. "HABB" → ["HA", "BB"]
     */
    private function splitOnderdeelCodes(string $onderdeelStr): array
    {
        $codes = [];
        for ($i = 0; $i < strlen($onderdeelStr); $i += 2) {
            $code = substr($onderdeelStr, $i, 2);
            if (strlen($code) === 2) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
