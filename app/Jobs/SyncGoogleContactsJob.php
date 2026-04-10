<?php

namespace App\Jobs;

use App\Models\Relatie;
use App\Services\Google\GoogleContactSyncService;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncGoogleContactsJob
{
    use Dispatchable;

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
}
