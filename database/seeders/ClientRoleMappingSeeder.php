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
        $client = Client::where('revoked', false)->first();

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

        // Ordered by priority (0 = highest). When a user has multiple
        // matching relatie types, the highest-priority mapping wins.
        $mappings = [
            ['type' => 'dirigent',       'role' => 'editor'],
            ['type' => 'bestuur',        'role' => 'editor'],
            ['type' => 'docent',         'role' => 'editor'],
            ['type' => 'lid',            'role' => 'subscriber'],
            ['type' => 'donateur',       'role' => 'subscriber'],
            ['type' => 'vrijwilliger',   'role' => 'subscriber'],
            ['type' => 'contactpersoon', 'role' => 'subscriber'],
        ];

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
