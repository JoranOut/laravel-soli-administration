<?php

namespace App\Observers;

use App\Jobs\SyncGoogleContactsJob;
use App\Models\Email;

class EmailGoogleSyncObserver
{
    public function saved(Email $email): void
    {
        if (GoogleContactSyncObserver::$disabled) {
            return;
        }

        SyncGoogleContactsJob::dispatch($email->relatie_id)->afterResponse();
    }

    public function deleted(Email $email): void
    {
        if (GoogleContactSyncObserver::$disabled) {
            return;
        }

        SyncGoogleContactsJob::dispatch($email->relatie_id)->afterResponse();
    }
}
