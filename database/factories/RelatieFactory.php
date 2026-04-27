<?php

namespace Database\Factories;

use App\Models\Relatie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Relatie>
 */
class RelatieFactory extends Factory
{
    protected $model = Relatie::class;

    private static int $relatieNummer = 10000;

    public function definition(): array
    {
        $geslacht = $this->faker->randomElement(['M', 'V', 'O']);
        $gender = $geslacht === 'M' ? 'male' : ($geslacht === 'V' ? 'female' : null);

        return [
            'relatie_nummer' => self::$relatieNummer++,
            'voornaam' => $gender ? $this->faker->firstName($gender) : $this->faker->firstName(),
            'tussenvoegsel' => $this->faker->optional(0.3)->randomElement(['van', 'de', 'van de', 'van der', 'den', 'van den']),
            'achternaam' => $this->faker->lastName(),
            'geslacht' => $geslacht,
            'geboortedatum' => $this->faker->dateTimeBetween('-70 years', '-8 years'),
            'actief' => true,
            'geboorteplaats' => $this->faker->city(),
            'nationaliteit' => 'Nederlandse',
        ];
    }

    public function inactief(): static
    {
        return $this->state(fn () => ['actief' => false]);
    }
}
