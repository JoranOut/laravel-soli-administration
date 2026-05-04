<?php

namespace Database\Seeders;

use App\Models\InstrumentFamilie;
use App\Models\InstrumentSoort;
use Illuminate\Database\Seeder;

class InstrumentSoortSeeder extends Seeder
{
    public function run(): void
    {
        $soorten = [
            // Bas
            ['naam' => 'Bas', 'familie' => 'Bas'],
            ['naam' => 'BesBas', 'familie' => 'Bas'],
            ['naam' => 'Contrabas', 'familie' => 'Bas'],
            ['naam' => 'Esbas', 'familie' => 'Bas'],
            ['naam' => 'Sousafoon', 'familie' => 'Bas'],

            // Directiepartijen
            ['naam' => 'Dirigent', 'familie' => 'Directiepartijen'],
            ['naam' => 'Partituur', 'familie' => 'Directiepartijen'],
            ['naam' => 'Tamboer-maître', 'familie' => 'Directiepartijen'],

            // Diverse
            ['naam' => 'Harp', 'familie' => 'Diverse'],
            ['naam' => 'Majorette', 'familie' => 'Diverse'],
            ['naam' => 'Strijk', 'familie' => 'Diverse'],
            ['naam' => 'Vlaggenwacht', 'familie' => 'Diverse'],

            // Dwarsfluit
            ['naam' => 'Dwarsfluit', 'familie' => 'Dwarsfluit'],
            ['naam' => 'Piccolo', 'familie' => 'Dwarsfluit'],

            // Fagot
            ['naam' => 'Contrafagot', 'familie' => 'Fagot'],
            ['naam' => 'Fagot', 'familie' => 'Fagot'],

            // Gitaar
            ['naam' => 'Basgitaar', 'familie' => 'Gitaar'],
            ['naam' => 'Gitaar', 'familie' => 'Gitaar'],

            // Hobo
            ['naam' => 'Althobo', 'familie' => 'Hobo'],
            ['naam' => 'Hobo', 'familie' => 'Hobo'],

            // Hoorn
            ['naam' => 'Hoorn', 'familie' => 'Hoorn'],

            // Klarinet
            ['naam' => 'Altklarinet', 'familie' => 'Klarinet'],
            ['naam' => 'Basklarinet', 'familie' => 'Klarinet'],
            ['naam' => 'Besklarinet', 'familie' => 'Klarinet'],
            ['naam' => 'Contrabasklarinet', 'familie' => 'Klarinet'],
            ['naam' => 'Esklarinet', 'familie' => 'Klarinet'],
            ['naam' => 'Klarinet', 'familie' => 'Klarinet'],

            // Klein koper
            ['naam' => 'Bugel', 'familie' => 'Klein koper'],
            ['naam' => 'Cornet', 'familie' => 'Klein koper'],
            ['naam' => 'Trompet', 'familie' => 'Klein koper'],

            // Saxofoon
            ['naam' => 'Altsaxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Baritonsaxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Bassaxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Contrabassaxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Saxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Sopraansaxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Tenorsaxofoon', 'familie' => 'Saxofoon'],

            // Slagwerk
            ['naam' => 'Bekken', 'familie' => 'Slagwerk'],
            ['naam' => 'Buisklokken', 'familie' => 'Slagwerk'],
            ['naam' => 'Drumstel', 'familie' => 'Slagwerk'],
            ['naam' => 'Kleine trom', 'familie' => 'Slagwerk'],
            ['naam' => 'Klokkenspel', 'familie' => 'Slagwerk'],
            ['naam' => 'Marimba', 'familie' => 'Slagwerk'],
            ['naam' => 'Melodisch slagwerk', 'familie' => 'Slagwerk'],
            ['naam' => 'Paradetrom', 'familie' => 'Slagwerk'],
            ['naam' => 'Pauken', 'familie' => 'Slagwerk'],
            ['naam' => 'Percussion', 'familie' => 'Slagwerk'],
            ['naam' => 'Slagwerk', 'familie' => 'Slagwerk'],
            ['naam' => 'Trio tom', 'familie' => 'Slagwerk'],
            ['naam' => 'Trom', 'familie' => 'Slagwerk'],
            ['naam' => 'Vibrafoon', 'familie' => 'Slagwerk'],
            ['naam' => 'Xylofoon', 'familie' => 'Slagwerk'],

            // Toetsen
            ['naam' => 'Keyboard', 'familie' => 'Toetsen'],
            ['naam' => 'Orgel', 'familie' => 'Toetsen'],
            ['naam' => 'Piano', 'familie' => 'Toetsen'],

            // Trombone
            ['naam' => 'Bastrombone', 'familie' => 'Trombone'],
            ['naam' => 'Trombone', 'familie' => 'Trombone'],

            // Tuba
            ['naam' => 'Althoorn', 'familie' => 'Tuba'],
            ['naam' => 'Bariton', 'familie' => 'Tuba'],
            ['naam' => 'Euphonium', 'familie' => 'Tuba'],
            ['naam' => 'Tuba', 'familie' => 'Tuba'],

            // Zang
            ['naam' => 'Zang', 'familie' => 'Zang'],
        ];

        // Create families first
        $familieNames = collect($soorten)->pluck('familie')->unique();
        $familieMap = [];
        foreach ($familieNames as $naam) {
            $familie = InstrumentFamilie::updateOrCreate(['naam' => $naam]);
            $familieMap[$naam] = $familie->id;
        }

        // Create soorten with FK
        foreach ($soorten as $soort) {
            InstrumentSoort::updateOrCreate(
                ['naam' => $soort['naam']],
                ['instrument_familie_id' => $familieMap[$soort['familie']]]
            );
        }
    }
}
