<?php

namespace Database\Seeders;

use App\Models\SoortContributie;
use Illuminate\Database\Seeder;

class SoortContributieSeeder extends Seeder
{
    public function run(): void
    {
        $soorten = [
            ['naam' => 'Lidmaatschap', 'beschrijving' => 'Jaarlijkse lidmaatschapsbijdrage'],
            ['naam' => 'Lesgeld', 'beschrijving' => 'Bijdrage voor muziekles'],
            ['naam' => 'Instrument huur', 'beschrijving' => 'Huur van verenigingsinstrument'],
        ];

        foreach ($soorten as $soort) {
            SoortContributie::firstOrCreate(
                ['naam' => $soort['naam']],
                ['beschrijving' => $soort['beschrijving']]
            );
        }
    }
}
