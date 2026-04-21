<?php

namespace Database\Factories;

use App\Models\Onderdeel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Onderdeel>
 */
class OnderdeelFactory extends Factory
{
    protected $model = Onderdeel::class;

    public function definition(): array
    {
        return [
            'naam' => $this->faker->unique()->words(2, true),
            'type' => $this->faker->randomElement(['muziekgroep', 'commissie', 'bestuur', 'staff', 'overig']),
            'afkorting' => null,
            'beschrijving' => $this->faker->optional()->sentence(),
            'actief' => true,
        ];
    }

    public function withAfkorting(): static
    {
        return $this->state(fn (array $attributes) => [
            'afkorting' => strtoupper($this->faker->unique()->lexify('??')),
        ]);
    }
}
