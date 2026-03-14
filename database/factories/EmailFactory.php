<?php

namespace Database\Factories;

use App\Models\Email;
use App\Models\Relatie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Email>
 */
class EmailFactory extends Factory
{
    protected $model = Email::class;

    public function definition(): array
    {
        return [
            'relatie_id' => Relatie::factory(),
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
