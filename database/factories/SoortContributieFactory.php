<?php

namespace Database\Factories;

use App\Models\SoortContributie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SoortContributie>
 */
class SoortContributieFactory extends Factory
{
    protected $model = SoortContributie::class;

    public function definition(): array
    {
        return [
            'naam' => $this->faker->unique()->word(),
            'beschrijving' => $this->faker->optional()->sentence(),
        ];
    }
}
