<?php

namespace Database\Seeders;

use App\Models\OauthClientSetting;
use Illuminate\Database\Seeder;
use Laravel\Passport\Client;

class OauthClientSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedWebsiteClient();
        $this->seedMuziekClient();
    }

    private function seedWebsiteClient(): void
    {
        $client = Client::updateOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            [
                'name' => 'Soli Website',
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

    private function seedMuziekClient(): void
    {
        $client = Client::updateOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000002'],
            [
                'name' => 'Soli Muziekbibliotheek',
                'secret' => 'muziek-test-secret',
                'redirect_uris' => ['http://localhost:8001/auth/callback'],
                'grant_types' => ['authorization_code', 'refresh_token'],
                'revoked' => false,
            ]
        );

        OauthClientSetting::updateOrCreate(
            ['client_id' => $client->id],
            [
                'type' => 'muziek_bibliotheek',
                'skip_authorization' => true,
            ]
        );
    }
}
