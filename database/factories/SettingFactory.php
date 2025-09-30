<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'key'   => $this->faker->unique()->slug(2), // e.g. "site-name"
            'value' => (string) $this->faker->words(2, true),
        ];
    }

    /** State for the sales tax setting */
    public function taxRate(float $min = 0.05, float $max = 0.12): self
    {
        return $this->state(fn () => [
            'key'   => 'tax_rate',
            // store as string like your config (e.g., "0.08")
            'value' => number_format($this->faker->randomFloat(3, $min, $max), 2, '.', ''),
        ]);
    }
}
