<?php

namespace Database\Factories;

use App\Models\RelatieType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RelatieType>
 */
class RelatieTypeFactory extends Factory
{
    protected $model = RelatieType::class;

    public function definition(): array
    {
        return [
            'naam' => $this->faker->unique()->word(),
        ];
    }
}
