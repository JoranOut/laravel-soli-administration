<?php

namespace Database\Seeders;

use App\Models\RelatieType;
use Illuminate\Database\Seeder;

class RelatieTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['donateur', 'lid', 'docent', 'dirigent', 'bestuur', 'contactpersoon', 'vrijwilliger'];

        foreach ($types as $type) {
            RelatieType::firstOrCreate(['naam' => $type]);
        }
    }
}
