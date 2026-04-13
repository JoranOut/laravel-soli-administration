<?php

namespace Database\Seeders;

use App\Models\OauthClientSetting;
use Illuminate\Database\Seeder;
use Laravel\Passport\Client;

class OauthClientSeeder extends Seeder
{
    public function run(): void
    {
        $client = Client::updateOrCreate(
            ['name' => 'Soli Website'],
            [
                'secret' => 'test-secret',
                'redirect_uris' => ['http://localhost:8080/callback'],
                'grant_types' => ['authorization_code', 'refresh_token'],
                'revoked' => false,
            ]
        );

        OauthClientSetting::updateOrCreate(
            ['client_id' => $client->id],
            [
                'type' => 'wordpress',
                'skip_authorization' => true,
            ]
        );
    }
}
