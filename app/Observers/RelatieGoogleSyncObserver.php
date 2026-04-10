<?php

namespace App\Observers;

use App\Jobs\SyncGoogleContactsJob;
use App\Models\Relatie;

class RelatieGoogleSyncObserver
{
    public function updated(Relatie $relatie): void
    {
        if (GoogleContactSyncObserver::$disabled) {
            return;
        }

        $syncFields = ['voornaam', 'tussenvoegsel', 'achternaam', 'actief'];

        if (! $relatie->wasChanged($syncFields)) {
            return;
        }

        SyncGoogleContactsJob::dispatch($relatie->id);
    }
}
