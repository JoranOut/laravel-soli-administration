<?php

namespace Database\Seeders;

use App\Models\Onderdeel;
use Illuminate\Database\Seeder;

class OnderdeelSeeder extends Seeder
{
    public function run(): void
    {
        $onderdelen = [
            // Orkesten
            ['naam' => 'Harmonie orkest', 'afkorting' => 'HA', 'type' => 'orkest'],
            ['naam' => 'Klein Orkest', 'afkorting' => 'KO', 'type' => 'orkest'],
            ['naam' => 'Bigband', 'afkorting' => 'BB', 'type' => 'orkest'],
            ['naam' => 'Slagwerkgroep', 'afkorting' => 'SG', 'type' => 'orkest'],

            // Ensembles
            ['naam' => 'Kerstensembles', 'afkorting' => null, 'type' => 'ensemble'],
            ['naam' => 'Pietenband', 'afkorting' => null, 'type' => 'ensemble'],
            ['naam' => 'Marsorkest', 'afkorting' => 'MO', 'type' => 'ensemble'],
            ['naam' => 'Funband', 'afkorting' => 'FB', 'type' => 'ensemble'],
            ['naam' => 'Oud Goud', 'afkorting' => 'OU', 'type' => 'ensemble'],
            ['naam' => 'Oude Glorie', 'afkorting' => 'OG', 'type' => 'ensemble'],
            ['naam' => 'TwirlTeam', 'afkorting' => 'TW', 'type' => 'ensemble'],
            ['naam' => 'Stil Orkest', 'afkorting' => null, 'type' => 'ensemble'],

            // Opleidingsgroepen
            ['naam' => 'Blokfluitklas', 'afkorting' => null, 'type' => 'opleidingsgroep'],
            ['naam' => 'Slagwerkklas', 'afkorting' => null, 'type' => 'opleidingsgroep'],
            ['naam' => 'Volwassenen opstapklas', 'afkorting' => 'SV', 'type' => 'opleidingsgroep'],
            ['naam' => 'Samenspelklas', 'afkorting' => 'SA', 'type' => 'opleidingsgroep'],
            ['naam' => 'Opleidingsorkest', 'afkorting' => 'OL', 'type' => 'opleidingsgroep'],
            ['naam' => 'Opstapklas', 'afkorting' => 'OK', 'type' => 'opleidingsgroep'],

            // Bestuur & commissies
            ['naam' => 'Bestuur', 'afkorting' => null, 'type' => 'bestuur'],
            ['naam' => 'Evenementencommissie', 'afkorting' => null, 'type' => 'commissie'],
            ['naam' => 'Muziekcommissie', 'afkorting' => null, 'type' => 'commissie'],

            // Staff & overig
            ['naam' => 'Dirigenten', 'afkorting' => null, 'type' => 'staff'],
            ['naam' => 'Overig', 'afkorting' => null, 'type' => 'overig'],
        ];

        foreach ($onderdelen as $onderdeel) {
            Onderdeel::updateOrCreate(
                ['naam' => $onderdeel['naam']],
                [
                    'afkorting' => $onderdeel['afkorting'],
                    'type' => $onderdeel['type'],
                    'actief' => true,
                ]
            );
        }
    }
}
