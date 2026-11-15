<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Once someone logs in to or sets a password on an account the member sync split off,
 * the account is in use and the sync must no longer fold it back into the family account.
 */
class ClearSplitFromUser
{
    public function handle(Login|PasswordReset $event): void
    {
        $user = $event->user;

        if ($user instanceof User && $user->split_from_user_id !== null) {
            $user->forceFill(['split_from_user_id' => null])->save();
        }
    }
}
