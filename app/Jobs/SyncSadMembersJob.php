<?php

namespace App\Jobs;

use App\Models\JobStatus;
use App\Models\SadSyncLog;
use App\Services\Sad\SadSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSadMembersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // A sync fetches three pages per member, so 200 members is over 600 requests
    // and minutes of work — far past the 60s default, which timed it out three
    // times over and left its status on "running". Never retry it: each attempt
    // is another 600 requests at SAD for the same outcome. The queue's retry_after
    // must exceed this timeout (DB_QUEUE_RETRY_AFTER), or the worker re-reserves
    // the job while it is still running and two syncs overlap.
    public int $tries = 1;

    public int $timeout = 1800;

    public function handle(SadSyncService $syncService): void
    {
        $syncService->syncAll();
    }

    /**
     * A timeout or crash kills the sync mid-run, leaving its status on "running"
     * forever — indistinguishable from a sync that is still working. Close it out.
     */
    public function failed(?\Throwable $exception): void
    {
        $message = 'Sync attempt failed: '.($exception?->getMessage() ?? 'killed (timeout or worker stop)');

        SadSyncLog::where('status', 'running')->update([
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => now(),
        ]);

        JobStatus::where('name', 'sad-sync')
            ->where('status', 'running')
            ->first()
            ?->markFailed($message);
    }
}
