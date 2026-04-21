<?php

namespace Database\Seeders;

use App\Models\Onderdeel;
use Illuminate\Database\Seeder;

class OnderdeelSeeder extends Seeder
{
    public function run(): void
    {
        $onderdelen = [
            // Muziekgroepen
            ['naam' => 'Harmonie orkest', 'afkorting' => 'HA', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Klein Orkest', 'afkorting' => 'KO', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Bigband', 'afkorting' => 'BB', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Slagwerkgroep', 'afkorting' => 'SG', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Drumfanfare', 'afkorting' => 'DF', 'type' => 'muziekgroep', 'actief' => false],
            ['naam' => 'Kerstensembles', 'afkorting' => null, 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Pietenband', 'afkorting' => null, 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Marsorkest', 'afkorting' => 'MO', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Funband', 'afkorting' => 'FB', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Oud Goud', 'afkorting' => 'OU', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Oude Glorie', 'afkorting' => 'OG', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'TwirlTeam', 'afkorting' => 'TW', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Stil Orkest', 'afkorting' => null, 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Blokfluitklas', 'afkorting' => null, 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Slagwerkklas', 'afkorting' => 'SK', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Volwassenen opstapklas', 'afkorting' => 'SV', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Samenspelklas', 'afkorting' => 'SA', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Opleidingsorkest', 'afkorting' => 'OL', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Opstapklas', 'afkorting' => 'OK', 'type' => 'muziekgroep', 'actief' => true],
            ['naam' => 'Leerlingen', 'afkorting' => 'LL', 'type' => 'muziekgroep', 'actief' => false],
            ['naam' => 'Kennismakingsklas', 'afkorting' => 'KK', 'type' => 'muziekgroep', 'actief' => false],

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
