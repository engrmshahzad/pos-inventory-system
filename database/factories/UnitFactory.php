<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unit>
 */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Pieces', 'short_name' => fake()->unique()->lexify('u???'), 'allow_decimal' => false];
    }

    public function decimal(): static
    {
        return $this->state(['name' => 'Kilogram', 'allow_decimal' => true]);
    }
}
