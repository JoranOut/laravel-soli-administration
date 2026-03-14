<?php

namespace Database\Factories;

use App\Models\GiroGegeven;
use App\Models\Relatie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiroGegeven>
 */
class GiroGegevenFactory extends Factory
{
    protected $model = GiroGegeven::class;

    public function definition(): array
    {
        return [
            'relatie_id' => Relatie::factory(),
            'iban' => $this->faker->iban('NL'),
            'bic' => $this->faker->optional()->swiftBicNumber(),
            'tenaamstelling' => $this->faker->name(),
            'machtiging' => $this->faker->boolean(70),
        ];
    }
}
