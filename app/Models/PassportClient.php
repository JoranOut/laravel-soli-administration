<?php

namespace App\Models;

use Laravel\Passport\Client;

class PassportClient extends Client
{
    public function skipsAuthorization(): bool
    {
        $setting = OauthClientSetting::where('client_id', $this->id)->first();

        return $setting?->skip_authorization ?? false;
    }
}
