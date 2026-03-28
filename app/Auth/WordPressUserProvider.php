<?php

namespace App\Auth;

use App\Support\Phpass;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class WordPressUserProvider extends EloquentUserProvider
{
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials['password'];
        $hash = $user->getAuthPassword();

        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            if (Phpass::check($plain, $hash)) {
                $user->forceFill(['password' => Hash::make($plain)])->save();

                return true;
            }

            return false;
        }

        return parent::validateCredentials($user, $credentials);
    }
}
