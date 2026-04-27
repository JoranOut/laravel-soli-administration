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
            // Trompet
            ['naam' => 'Trompet', 'familie' => 'Trompet'],
            ['naam' => 'Cornet', 'familie' => 'Trompet'],
            ['naam' => 'Bugel', 'familie' => 'Trompet'],

            // Klarinet
            ['naam' => 'Klarinet', 'familie' => 'Klarinet'],
            ['naam' => 'Besklarinet', 'familie' => 'Klarinet'],
            ['naam' => 'Basklarinet', 'familie' => 'Klarinet'],
            ['naam' => 'Esklarinet', 'familie' => 'Klarinet'],
            ['naam' => 'Altklarinet', 'familie' => 'Klarinet'],
            ['naam' => 'Contrabasklarinet', 'familie' => 'Klarinet'],

            // Saxofoon
            ['naam' => 'Saxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Altsaxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Tenorsaxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Baritonsaxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Sopraansaxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Bassaxofoon', 'familie' => 'Saxofoon'],
            ['naam' => 'Contrabassaxofoon', 'familie' => 'Saxofoon'],

            // Dwarsfluit
            ['naam' => 'Dwarsfluit', 'familie' => 'Dwarsfluit'],
            ['naam' => 'Piccolo', 'familie' => 'Dwarsfluit'],

            // Trombone
            ['naam' => 'Trombone', 'familie' => 'Trombone'],
            ['naam' => 'Bastrombone', 'familie' => 'Trombone'],

            // Hoorn
            ['naam' => 'Hoorn', 'familie' => 'Hoorn'],
            ['naam' => 'Althoorn', 'familie' => 'Hoorn'],

            // Tuba
            ['naam' => 'Tuba', 'familie' => 'Bas'],
            ['naam' => 'Sousafoon', 'familie' => 'Bas'],

            // Bariton
            ['naam' => 'Bariton', 'familie' => 'Bariton'],
            ['naam' => 'Euphonium', 'familie' => 'Bariton'],

            // Bas
            ['naam' => 'Besbas', 'familie' => 'Bas'],
            ['naam' => 'Esbas', 'familie' => 'Bas'],
            ['naam' => 'Contrabas', 'familie' => 'Bas'],
            ['naam' => 'Basgitaar', 'familie' => 'Gitaar'],

            // Hobo
            ['naam' => 'Hobo', 'familie' => 'Hobo'],
            ['naam' => 'Althobo', 'familie' => 'Hobo'],

            // Fagot
            ['naam' => 'Fagot', 'familie' => 'Fagot'],
            ['naam' => 'Contrafagot', 'familie' => 'Fagot'],

            // Slagwerk
            ['naam' => 'Slagwerk', 'familie' => 'Slagwerk'],
            ['naam' => 'Melodisch slagwerk', 'familie' => 'Slagwerk'],
            ['naam' => 'Paradetrom', 'familie' => 'Slagwerk'],
            ['naam' => 'Kleine trom', 'familie' => 'Slagwerk'],
            ['naam' => 'Trom', 'familie' => 'Slagwerk'],
            ['naam' => 'Trio tom', 'familie' => 'Slagwerk'],
            ['naam' => 'Bekken', 'familie' => 'Slagwerk'],
            ['naam' => 'Pauken', 'familie' => 'Slagwerk'],
            ['naam' => 'Marimba', 'familie' => 'Slagwerk'],
            ['naam' => 'Vibrafoon', 'familie' => 'Slagwerk'],
            ['naam' => 'Xylofoon', 'familie' => 'Slagwerk'],
            ['naam' => 'Tamboer-maître', 'familie' => 'Tamboer-maître'],
            ['naam' => 'Percussion', 'familie' => 'Slagwerk'],

            // Majorette
            ['naam' => 'Majorette', 'familie' => 'Majorette'],
            ['naam' => 'Vlaggenwacht', 'familie' => 'Majorette'],

            // Gitaar
            ['naam' => 'Gitaar', 'familie' => 'Gitaar'],

            // Piano
            ['naam' => 'Keyboard', 'familie' => 'Piano'],
            ['naam' => 'Piano', 'familie' => 'Piano'],

            // Zang
            ['naam' => 'Zang', 'familie' => 'Zang'],

            // Partituur
            ['naam' => 'Partituur', 'familie' => 'Partituur'],
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
