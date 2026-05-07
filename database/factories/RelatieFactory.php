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
        return [
            'relatie_nummer' => self::$relatieNummer++,
            'voornaam' => $this->faker->firstName(),
            'tussenvoegsel' => $this->faker->optional(0.3)->randomElement(['van', 'de', 'van de', 'van der', 'den', 'van den']),
            'achternaam' => $this->faker->lastName(),
            'geboortedatum' => $this->faker->dateTimeBetween('-70 years', '-8 years'),
            'actief' => true,
            'beheerd_in_admin' => false,
        ];
    }

    public function inactief(): static
    {
        return $this->state(fn () => ['actief' => false]);
    }

    public function beheerInAdmin(): static
    {
        return $this->state(fn () => ['beheerd_in_admin' => true]);
    }
}
