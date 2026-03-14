<?php

namespace Database\Factories;

use App\Models\Betaling;
use App\Models\TeBetakenContributie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Betaling>
 */
class BetalingFactory extends Factory
{
    protected $model = Betaling::class;

    public function definition(): array
    {
        return [
            'te_betalen_contributie_id' => TeBetakenContributie::factory(),
            'bedrag' => $this->faker->randomFloat(2, 10, 500),
            'datum' => $this->faker->date(),
            'methode' => $this->faker->optional()->randomElement(['bank', 'contant', 'pin']),
            'opmerking' => $this->faker->optional()->sentence(),
        ];
    }
}
