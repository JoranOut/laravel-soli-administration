<?php

namespace Database\Seeders;

use App\Models\RelatieType;
use Illuminate\Database\Seeder;

class RelatieTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['naam' => 'donateur', 'onderdeel_koppelbaar' => false],
            ['naam' => 'lid', 'onderdeel_koppelbaar' => false],
            ['naam' => 'docent', 'onderdeel_koppelbaar' => false],
            ['naam' => 'dirigent', 'onderdeel_koppelbaar' => true],
            ['naam' => 'bestuur', 'onderdeel_koppelbaar' => false],
            ['naam' => 'contactpersoon', 'onderdeel_koppelbaar' => true],
            ['naam' => 'vrijwilliger', 'onderdeel_koppelbaar' => false],
            ['naam' => 'projectdeelnemer', 'onderdeel_koppelbaar' => false],
        ];

        foreach ($types as $type) {
            RelatieType::updateOrCreate(
                ['naam' => $type['naam']],
                ['onderdeel_koppelbaar' => $type['onderdeel_koppelbaar']]
            );
        }
    }
}
