<?php

namespace Database\Factories;

use App\Models\Instrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instrument>
 */
class InstrumentFactory extends Factory
{
    protected $model = Instrument::class;

    private static int $instrumentNummer = 1;

    public function definition(): array
    {
        $soorten = ['Trompet', 'Klarinet', 'Saxofoon', 'Trombone', 'Hoorn', 'Tuba', 'Fluit', 'Hobo', 'Fagot', 'Slagwerk', 'Euphonium', 'Cornet'];
        $merken = ['Yamaha', 'Jupiter', 'Buffet Crampon', 'Selmer', 'Bach', 'Conn', 'King', 'Holton'];

        return [
            'nummer' => 'I-'.str_pad(self::$instrumentNummer++, 4, '0', STR_PAD_LEFT),
            'soort' => $this->faker->randomElement($soorten),
            'merk' => $this->faker->randomElement($merken),
            'model' => $this->faker->optional()->bothify('??-###'),
            'serienummer' => $this->faker->optional(0.7)->numerify('SN######'),
            'status' => 'beschikbaar',
            'eigendom' => 'soli',
            'aanschafjaar' => $this->faker->numberBetween(1990, 2025),
            'prijs' => $this->faker->randomFloat(2, 200, 5000),
        ];
    }

    public function inGebruik(): static
    {
        return $this->state(fn () => ['status' => 'in_gebruik']);
    }

    public function inReparatie(): static
    {
        return $this->state(fn () => ['status' => 'in_reparatie']);
    }
}
