<?php

namespace Database\Factories;

use App\Models\Tariefgroep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tariefgroep>
 */
class TariefgroepFactory extends Factory
{
    protected $model = Tariefgroep::class;

    public function definition(): array
    {
        return [
            'naam' => $this->faker->unique()->words(2, true),
            'beschrijving' => $this->faker->optional()->sentence(),
        ];
    }
}
