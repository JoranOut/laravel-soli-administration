<?php

namespace Database\Factories;

use App\Models\Contributie;
use App\Models\Relatie;
use App\Models\TeBetakenContributie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeBetakenContributie>
 */
class TeBetakenContributieFactory extends Factory
{
    protected $model = TeBetakenContributie::class;

    public function definition(): array
    {
        return [
            'relatie_id' => Relatie::factory(),
            'contributie_id' => Contributie::factory(),
            'jaar' => $this->faker->year(),
            'bedrag' => $this->faker->randomFloat(2, 10, 500),
            'status' => 'open',
        ];
    }
}
