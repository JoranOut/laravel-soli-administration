<?php

namespace Database\Factories;

use App\Models\Contributie;
use App\Models\SoortContributie;
use App\Models\Tariefgroep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contributie>
 */
class ContributieFactory extends Factory
{
    protected $model = Contributie::class;

    public function definition(): array
    {
        return [
            'tariefgroep_id' => Tariefgroep::factory(),
            'soort_contributie_id' => SoortContributie::factory(),
            'jaar' => $this->faker->year(),
            'bedrag' => $this->faker->randomFloat(2, 10, 500),
        ];
    }
}
