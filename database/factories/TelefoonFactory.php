<?php

namespace Database\Factories;

use App\Models\Relatie;
use App\Models\Telefoon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Telefoon>
 */
class TelefoonFactory extends Factory
{
    protected $model = Telefoon::class;

    public function definition(): array
    {
        return [
            'relatie_id' => Relatie::factory(),
            'nummer' => $this->faker->phoneNumber(),
        ];
    }
}
