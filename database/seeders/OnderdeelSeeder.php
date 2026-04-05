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
            ['naam' => 'Harmonie orkest', 'afkorting' => 'HA', 'type' => 'orkest', 'actief' => true],
            ['naam' => 'Klein Orkest', 'afkorting' => 'KO', 'type' => 'orkest', 'actief' => true],
            ['naam' => 'Bigband', 'afkorting' => 'BB', 'type' => 'orkest', 'actief' => true],
            ['naam' => 'Slagwerkgroep', 'afkorting' => 'SG', 'type' => 'orkest', 'actief' => true],
            ['naam' => 'Drumfanfare', 'afkorting' => 'DF', 'type' => 'orkest', 'actief' => false],

            // Ensembles
            ['naam' => 'Kerstensembles', 'afkorting' => null, 'type' => 'ensemble', 'actief' => true],
            ['naam' => 'Pietenband', 'afkorting' => null, 'type' => 'ensemble', 'actief' => true],
            ['naam' => 'Marsorkest', 'afkorting' => 'MO', 'type' => 'ensemble', 'actief' => true],
            ['naam' => 'Funband', 'afkorting' => 'FB', 'type' => 'ensemble', 'actief' => true],
            ['naam' => 'Oud Goud', 'afkorting' => 'OU', 'type' => 'ensemble', 'actief' => true],
            ['naam' => 'Oude Glorie', 'afkorting' => 'OG', 'type' => 'ensemble', 'actief' => true],
            ['naam' => 'TwirlTeam', 'afkorting' => 'TW', 'type' => 'ensemble', 'actief' => true],
            ['naam' => 'Stil Orkest', 'afkorting' => null, 'type' => 'ensemble', 'actief' => true],

            // Opleidingsgroepen
            ['naam' => 'Blokfluitklas', 'afkorting' => null, 'type' => 'opleidingsgroep', 'actief' => true],
            ['naam' => 'Slagwerkklas', 'afkorting' => 'SK', 'type' => 'opleidingsgroep', 'actief' => true],
            ['naam' => 'Volwassenen opstapklas', 'afkorting' => 'SV', 'type' => 'opleidingsgroep', 'actief' => true],
            ['naam' => 'Samenspelklas', 'afkorting' => 'SA', 'type' => 'opleidingsgroep', 'actief' => true],
            ['naam' => 'Opleidingsorkest', 'afkorting' => 'OL', 'type' => 'opleidingsgroep', 'actief' => true],
            ['naam' => 'Opstapklas', 'afkorting' => 'OK', 'type' => 'opleidingsgroep', 'actief' => true],
            ['naam' => 'Leerlingen', 'afkorting' => 'LL', 'type' => 'opleidingsgroep', 'actief' => false],
            ['naam' => 'Kennismakingsklas', 'afkorting' => 'KK', 'type' => 'opleidingsgroep', 'actief' => false],

            // Bestuur & commissies
            ['naam' => 'Bestuur', 'afkorting' => null, 'type' => 'bestuur', 'actief' => true],
            ['naam' => 'Evenementencommissie', 'afkorting' => null, 'type' => 'commissie', 'actief' => true],
            ['naam' => 'Muziekcommissie', 'afkorting' => null, 'type' => 'commissie', 'actief' => true],

            // Staff
            ['naam' => 'Dirigenten', 'afkorting' => null, 'type' => 'staff', 'actief' => true],

            // Overig
            ['naam' => 'Overig', 'afkorting' => 'OV', 'type' => 'overig', 'actief' => true],
            ['naam' => 'VL', 'afkorting' => 'VL', 'type' => 'overig', 'actief' => false],
            ['naam' => 'MA', 'afkorting' => 'MA', 'type' => 'overig', 'actief' => false],
            ['naam' => 'TA', 'afkorting' => 'TA', 'type' => 'overig', 'actief' => false],
            ['naam' => 'TK', 'afkorting' => 'TK', 'type' => 'overig', 'actief' => false],
            ['naam' => 'TAK', 'afkorting' => 'TAK', 'type' => 'overig', 'actief' => false],
            ['naam' => 'OP', 'afkorting' => 'OP', 'type' => 'overig', 'actief' => false],
        ];

        foreach ($onderdelen as $onderdeel) {
            Onderdeel::updateOrCreate(
                ['naam' => $onderdeel['naam']],
                [
                    'afkorting' => $onderdeel['afkorting'],
                    'type' => $onderdeel['type'],
                    'actief' => $onderdeel['actief'],
                ]
            );
        }
    }
}
