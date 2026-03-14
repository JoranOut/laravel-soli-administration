<?php

namespace Database\Factories;

use App\Models\Relatie;
use App\Models\RelatieSinds;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RelatieSinds>
 */
class RelatieSindsFactory extends Factory
{
    protected $model = RelatieSinds::class;

    public function definition(): array
    {
        return [
            'relatie_id' => Relatie::factory(),
            'lid_sinds' => $this->faker->date(),
            'lid_tot' => null,
            'reden_vertrek' => null,
        ];
    }
}
