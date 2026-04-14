<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

class PassportClient extends Client
{
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        $setting = OauthClientSetting::where('client_id', $this->id)->first();

        return $setting?->skip_authorization ?? false;
    }
}
