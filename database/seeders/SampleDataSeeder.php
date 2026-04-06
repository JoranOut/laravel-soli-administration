<?php

namespace Database\Seeders;

use App\Models\Adres;
use App\Models\Contributie;
use App\Models\Email;
use App\Models\Instrument;
use App\Models\InstrumentBespeler;
use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\SoortContributie;
use App\Models\Tariefgroep;
use App\Models\TeBetakenContributie;
use App\Models\Telefoon;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SampleDataSeeder extends Seeder
{
    private array $usedEmails = [];

    private function generateEmail(string $voornaam, string $achternaam): string
    {
        $base = Str::slug($voornaam).'.'.Str::slug($achternaam);
        $email = $base.'@example.com';
        $counter = 1;
        while (in_array($email, $this->usedEmails)) {
            $email = $base.$counter.'@example.com';
            $counter++;
        }
        $this->usedEmails[] = $email;

        return $email;
    }

    public function run(): void
    {
        $lidType = RelatieType::where('naam', 'lid')->first();
        $donateurType = RelatieType::where('naam', 'donateur')->first();
        $docentType = RelatieType::where('naam', 'docent')->first();
        $dirigentType = RelatieType::where('naam', 'dirigent')->first();
        $bestuurType = RelatieType::where('naam', 'bestuur')->first();

        $onderdelen = Onderdeel::all();
        $harmonieOrkest = $onderdelen->firstWhere('naam', 'Harmonie orkest');
        $kleinOrkest = $onderdelen->firstWhere('naam', 'Klein Orkest');
        $bigband = $onderdelen->firstWhere('naam', 'Bigband');

        // Create 35 leden
        $leden = Relatie::factory(35)->create();
        foreach ($leden as $lid) {
            $lid->types()->attach($lidType->id, [
                'van' => fake()->dateTimeBetween('-10 years', '-1 year')->format('Y-m-d'),
            ]);

            // Add contact info
            Adres::create([
                'relatie_id' => $lid->id,
                'straat' => fake()->streetName(),
                'huisnummer' => fake()->buildingNumber(),
                'postcode' => fake()->postcode(),
                'plaats' => fake()->randomElement(['Driehuis', 'Velsen', 'IJmuiden', 'Santpoort', 'Haarlem']),
            ]);

            $emailAddress = $this->generateEmail($lid->voornaam, $lid->achternaam);
            $email = Email::create([
                'relatie_id' => $lid->id,
                'email' => $emailAddress,
            ]);

            $user = User::create([
                'name' => $lid->voornaam.' '.$lid->achternaam,
                'email' => $emailAddress,
                'password' => Hash::make('password'),
            ]);
            $user->assignRole('member');
            $lid->update(['user_id' => $user->id]);

            Telefoon::create([
                'relatie_id' => $lid->id,
                'nummer' => fake()->phoneNumber(),
            ]);

            // Assign to random onderdeel(en)
            $selectedOnderdelen = $onderdelen
                ->whereIn('type', ['orkest', 'ensemble'])
                ->random(rand(1, 3));

            foreach ($selectedOnderdelen as $onderdeel) {
                $lid->onderdelen()->attach($onderdeel->id, [
                    'van' => fake()->dateTimeBetween('-8 years', '-1 year')->format('Y-m-d'),
                ]);
            }
        }

        // Create 10 donateurs
        $donateurs = Relatie::factory(10)->create();
        foreach ($donateurs as $donateur) {
            $donateur->types()->attach($donateurType->id, [
                'van' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
            ]);

            $emailAddress = $this->generateEmail($donateur->voornaam, $donateur->achternaam);
            $email = Email::create([
                'relatie_id' => $donateur->id,
                'email' => $emailAddress,
            ]);

            $user = User::create([
                'name' => $donateur->voornaam.' '.$donateur->achternaam,
                'email' => $emailAddress,
                'password' => Hash::make('password'),
            ]);
            $user->assignRole('member');
            $donateur->update(['user_id' => $user->id]);
        }

        // Create 3 docenten (1 is also lid)
        $opleidingsgroepen = $onderdelen->where('type', 'opleidingsgroep')->where('actief', true);
        $docenten = Relatie::factory(3)->create();
        foreach ($docenten as $i => $docent) {
            $docent->types()->attach($docentType->id, [
                'van' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
            ]);

            // First docent is also a lid
            if ($i === 0) {
                $docent->types()->attach($lidType->id, [
                    'van' => fake()->dateTimeBetween('-10 years', '-5 years')->format('Y-m-d'),
                ]);
            }

            $emailAddress = $this->generateEmail($docent->voornaam, $docent->achternaam);
            Email::create([
                'relatie_id' => $docent->id,
                'email' => $emailAddress,
            ]);

            $user = User::create([
                'name' => $docent->voornaam.' '.$docent->achternaam,
                'email' => $emailAddress,
                'password' => Hash::make('password'),
            ]);
            $user->assignRole('member');
            $docent->update(['user_id' => $user->id]);

            // Assign to a random training group
            if ($opleidingsgroepen->isNotEmpty()) {
                $docent->onderdelen()->attach($opleidingsgroepen->random()->id, [
                    'functie' => 'Docent',
                    'van' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
                ]);
            }
        }

        // Create 2 dirigenten (1 is also lid)
        $dirigenten = Relatie::factory(2)->create();
        foreach ($dirigenten as $i => $dirigent) {
            $dirigent->types()->attach($dirigentType->id, [
                'van' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
            ]);

            // First dirigent is also a lid
            if ($i === 0) {
                $dirigent->types()->attach($lidType->id, [
                    'van' => fake()->dateTimeBetween('-10 years', '-5 years')->format('Y-m-d'),
                ]);
            }

            $emailAddress = $this->generateEmail($dirigent->voornaam, $dirigent->achternaam);
            Email::create([
                'relatie_id' => $dirigent->id,
                'email' => $emailAddress,
            ]);

            $user = User::create([
                'name' => $dirigent->voornaam.' '.$dirigent->achternaam,
                'email' => $emailAddress,
                'password' => Hash::make('password'),
            ]);
            $user->assignRole('member');
            $dirigent->update(['user_id' => $user->id]);

            if ($harmonieOrkest) {
                $dirigent->onderdelen()->attach($harmonieOrkest->id, [
                    'functie' => 'Dirigent',
                    'van' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
                ]);
            }
        }

        // Create 5 bestuur members from existing leden
        $bestuurFuncties = ['Voorzitter', 'Secretaris', 'Penningmeester', 'Bestuurslid', 'Bestuurslid'];
        $bestuurLeden = $leden->take(5);
        foreach ($bestuurLeden as $i => $lid) {
            $lid->types()->attach($bestuurType->id, [
                'van' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
                'functie' => $bestuurFuncties[$i],
                'email' => strtolower(str_replace(' ', '', $bestuurFuncties[$i])).'@soli.nl',
            ]);
        }

        // Create 5 inactive relaties
        Relatie::factory(5)->inactief()->create()->each(function ($relatie) use ($lidType) {
            $van = fake()->dateTimeBetween('-10 years', '-5 years')->format('Y-m-d');
            $relatie->types()->attach($lidType->id, [
                'van' => $van,
                'tot' => fake()->dateTimeBetween('-4 years', '-1 year')->format('Y-m-d'),
            ]);
        });

        // Create instruments and assign some
        $instrumenten = Instrument::factory(30)->create();

        $beschikbareInstrumenten = $instrumenten->take(20);
        $ledenSubset = $leden->take(15);

        foreach ($ledenSubset as $i => $lid) {
            if ($i >= $beschikbareInstrumenten->count()) {
                break;
            }

            $instrument = $beschikbareInstrumenten[$i];
            $instrument->update(['status' => 'in_gebruik']);

            InstrumentBespeler::create([
                'instrument_id' => $instrument->id,
                'relatie_id' => $lid->id,
                'van' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            ]);
        }

        // Create 3 instruments in repair
        Instrument::factory(3)->inReparatie()->create();

        // Create contribution rates for current year
        $tariefgroepen = Tariefgroep::all();
        $soortContributies = SoortContributie::all();
        $currentYear = now()->year;

        foreach ($tariefgroepen as $tariefgroep) {
            foreach ($soortContributies as $soort) {
                $bedrag = match ($tariefgroep->naam) {
                    'Jeugd' => match ($soort->naam) {
                        'Lidmaatschap' => 75.00,
                        'Lesgeld' => 150.00,
                        'Instrument huur' => 50.00,
                        default => 0,
                    },
                    'Volwassen' => match ($soort->naam) {
                        'Lidmaatschap' => 120.00,
                        'Lesgeld' => 200.00,
                        'Instrument huur' => 75.00,
                        default => 0,
                    },
                    'Senior' => match ($soort->naam) {
                        'Lidmaatschap' => 90.00,
                        'Lesgeld' => 150.00,
                        'Instrument huur' => 50.00,
                        default => 0,
                    },
                    'Donateur' => match ($soort->naam) {
                        'Lidmaatschap' => 25.00,
                        default => 0,
                    },
                    default => 0,
                };

                if ($bedrag > 0) {
                    Contributie::create([
                        'tariefgroep_id' => $tariefgroep->id,
                        'soort_contributie_id' => $soort->id,
                        'jaar' => $currentYear,
                        'bedrag' => $bedrag,
                    ]);
                }
            }
        }

        // Create outstanding contributions for some leden
        $lidmaatschap = $soortContributies->firstWhere('naam', 'Lidmaatschap');
        $volwassenTariefgroep = $tariefgroepen->firstWhere('naam', 'Volwassen');
        $contributie = Contributie::where('tariefgroep_id', $volwassenTariefgroep->id)
            ->where('soort_contributie_id', $lidmaatschap->id)
            ->where('jaar', $currentYear)
            ->first();

        if ($contributie) {
            foreach ($leden->take(10) as $lid) {
                TeBetakenContributie::create([
                    'relatie_id' => $lid->id,
                    'contributie_id' => $contributie->id,
                    'jaar' => $currentYear,
                    'bedrag' => $contributie->bedrag,
                    'status' => fake()->randomElement(['open', 'open', 'betaald']),
                ]);
            }
        }
    }
}
