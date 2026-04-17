<?php

namespace Database\Seeders;

use App\Models\ClientRoleMapping;
use App\Models\OauthClientSetting;
use App\Models\RelatieType;
use App\Services\ClientRoleResolver;
use Illuminate\Database\Seeder;
use Laravel\Passport\Client;

class ClientRoleMappingSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedWebsiteMappings();
        $this->seedMuziekMappings();
    }

    private function seedWebsiteMappings(): void
    {
        $client = Client::where('name', 'Soli Website')->first();

        if (! $client) {
            return;
        }

        $setting = OauthClientSetting::updateOrCreate(
            ['client_id' => $client->id],
            [
                'type' => 'wordpress',
                'default_role' => ClientRoleResolver::NO_ACCESS,
            ]
        );

        $this->createMappings($setting, [
            ['type' => 'dirigent',       'role' => 'editor'],
            ['type' => 'bestuur',        'role' => 'editor'],
            ['type' => 'docent',         'role' => 'editor'],
            ['type' => 'lid',            'role' => 'subscriber'],
            ['type' => 'donateur',       'role' => 'subscriber'],
            ['type' => 'vrijwilliger',   'role' => 'subscriber'],
            ['type' => 'contactpersoon', 'role' => 'subscriber'],
        ]);
    }

    private function seedMuziekMappings(): void
    {
        $client = Client::where('name', 'Soli Muziekbibliotheek')->first();

        if (! $client) {
            return;
        }

        $setting = OauthClientSetting::updateOrCreate(
            ['client_id' => $client->id],
            [
                'type' => 'laravel',
                'default_role' => ClientRoleResolver::NO_ACCESS,
            ]
        );

        // dirigent/docent can manage sheets, lid can view/download
        $this->createMappings($setting, [
            ['type' => 'dirigent', 'role' => 'editor'],
            ['type' => 'docent',   'role' => 'editor'],
            ['type' => 'bestuur',  'role' => 'editor'],
            ['type' => 'lid',      'role' => 'viewer'],
        ]);
    }

    /**
     * @param  array<int, array{type: string, role: string}>  $mappings
     */
    private function createMappings(OauthClientSetting $setting, array $mappings): void
    {
        foreach ($mappings as $priority => $mapping) {
            $type = RelatieType::where('naam', $mapping['type'])->first();

            if ($type) {
                ClientRoleMapping::updateOrCreate(
                    [
                        'client_setting_id' => $setting->id,
                        'relatie_type_id' => $type->id,
                    ],
                    [
                        'mapped_role' => $mapping['role'],
                        'priority' => $priority,
                    ]
                );
            }
        }
    }
}
