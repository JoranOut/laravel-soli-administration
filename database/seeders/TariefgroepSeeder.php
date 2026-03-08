<?php

namespace Database\Seeders;

use App\Models\Tariefgroep;
use Illuminate\Database\Seeder;

class TariefgroepSeeder extends Seeder
{
    public function run(): void
    {
        $groepen = [
            ['naam' => 'Jeugd', 'beschrijving' => 'Leden tot 18 jaar'],
            ['naam' => 'Volwassen', 'beschrijving' => 'Leden vanaf 18 jaar'],
            ['naam' => 'Senior', 'beschrijving' => 'Leden vanaf 65 jaar'],
            ['naam' => 'Donateur', 'beschrijving' => 'Donateurs van de vereniging'],
        ];

        foreach ($groepen as $groep) {
            Tariefgroep::firstOrCreate(
                ['naam' => $groep['naam']],
                ['beschrijving' => $groep['beschrijving']]
            );
        }
    }
}
