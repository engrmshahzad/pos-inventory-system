<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'color' => '#4F46E5', 'icon' => 'package', 'is_active' => true];
    }
}
