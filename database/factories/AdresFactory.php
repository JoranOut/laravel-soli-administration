<?php

namespace Database\Factories;

use App\Models\Adres;
use App\Models\Relatie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Adres>
 */
class AdresFactory extends Factory
{
    protected $model = Adres::class;

    public function definition(): array
    {
        return [
            'relatie_id' => Relatie::factory(),
            'straat' => $this->faker->streetName(),
            'huisnummer' => (string) $this->faker->buildingNumber(),
            'huisnummer_toevoeging' => $this->faker->optional(0.3)->randomElement(['A', 'B', 'bis']),
            'postcode' => $this->faker->postcode(),
            'plaats' => $this->faker->city(),
            'land' => 'Nederland',
        ];
    }
}
