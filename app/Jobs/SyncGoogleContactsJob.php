<?php

namespace App\Jobs;

use App\Models\GoogleContactSyncLog;
use App\Models\JobStatus;
use App\Models\Relatie;
use App\Services\Google\GoogleContactSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncGoogleContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // A full sync legitimately runs for many minutes. Never retry it:
    // a re-delivered attempt overlapping a still-running one creates
    // duplicate contacts in Google that nothing cleans up. The queue's
    // retry_after must exceed this timeout (DB_QUEUE_RETRY_AFTER).
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public ?int $relatieId = null,
    ) {}

    public function handle(GoogleContactSyncService $syncService): void
    {
        if (! config('services.google.contacts_sync_enabled')) {
            return;
        }

        if ($this->relatieId) {
            $relatie = Relatie::with(['emails', 'onderdelen'])->find($this->relatieId);

            if (! $relatie) {
                return;
            }

            $syncService->syncRelatie($relatie);
        } else {
            $syncService->syncAll();
        }
    }

    /**
     * A timeout or crash kills the sync mid-run, leaving its log row on
     * "running" forever. Close it out as failed right away instead.
     */
    public function failed(?\Throwable $exception): void
    {
        $message = 'Sync attempt failed: '.($exception?->getMessage() ?? 'killed (timeout or worker stop)');

        GoogleContactSyncLog::where('status', 'running')
            ->where('type', $this->relatieId ? 'relatie' : 'full')
            ->when($this->relatieId, fn ($q) => $q->where('relatie_id', $this->relatieId))
            ->update([
                'status' => 'failed',
                'error_message' => $message,
                'completed_at' => now(),
            ]);

        JobStatus::where('name', 'google-contacts-sync')
            ->where('status', 'running')
            ->first()
            ?->markFailed($message);
    }
}
