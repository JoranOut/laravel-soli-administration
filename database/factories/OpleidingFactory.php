<?php

namespace Database\Factories;

use App\Models\Opleiding;
use App\Models\Relatie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opleiding>
 */
class OpleidingFactory extends Factory
{
    protected $model = Opleiding::class;

    public function definition(): array
    {
        return [
            'relatie_id' => Relatie::factory(),
            'naam' => $this->faker->words(3, true),
            'instituut' => $this->faker->optional()->company(),
            'instrument' => $this->faker->optional()->word(),
            'diploma' => $this->faker->optional()->word(),
            'datum_start' => $this->faker->optional()->date(),
            'datum_einde' => null,
            'opmerking' => $this->faker->optional()->sentence(),
        ];
    }
}
