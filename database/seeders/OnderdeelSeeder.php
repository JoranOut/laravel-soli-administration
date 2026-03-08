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
            ['naam' => 'Harmonie orkest', 'type' => 'orkest'],
            ['naam' => 'Klein Orkest', 'type' => 'orkest'],
            ['naam' => 'Bigband', 'type' => 'orkest'],
            ['naam' => 'Slagwerkgroep', 'type' => 'orkest'],

            // Ensembles
            ['naam' => 'Kerstensembles', 'type' => 'ensemble'],
            ['naam' => 'Pietenband', 'type' => 'ensemble'],
            ['naam' => 'Marsorkest', 'type' => 'ensemble'],
            ['naam' => 'Funband', 'type' => 'ensemble'],
            ['naam' => 'Oud Goud', 'type' => 'ensemble'],
            ['naam' => 'TwirlTeam', 'type' => 'ensemble'],
            ['naam' => 'Stil Orkest', 'type' => 'ensemble'],

            // Opleidingsgroepen
            ['naam' => 'Blokfluitklas', 'type' => 'opleidingsgroep'],
            ['naam' => 'Slagwerkklas', 'type' => 'opleidingsgroep'],
            ['naam' => 'Volwassenen opstapklas', 'type' => 'opleidingsgroep'],
            ['naam' => 'Samenspelklas', 'type' => 'opleidingsgroep'],
            ['naam' => 'Opleidingsorkest', 'type' => 'opleidingsgroep'],

            // Bestuur & commissies
            ['naam' => 'Bestuur', 'type' => 'bestuur'],
            ['naam' => 'Evenementencommissie', 'type' => 'commissie'],
            ['naam' => 'Muziekcommissie', 'type' => 'commissie'],
        ];

        foreach ($onderdelen as $onderdeel) {
            Onderdeel::firstOrCreate(
                ['naam' => $onderdeel['naam']],
                ['type' => $onderdeel['type'], 'actief' => true]
            );
        }
    }
}
