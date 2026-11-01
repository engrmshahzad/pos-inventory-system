<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        $city = fake()->unique()->randomElement(['Lahore', 'Multan', 'Quetta', 'Peshawar', 'Larkana', 'Nawabshah', 'Mirpurkhas', 'Faisalabad']);

        return [
            'name' => "{$city} Branch",
            'code' => Str::upper(Str::substr($city, 0, 2).fake()->unique()->randomLetter()),
            'city' => $city,
            'phone' => fake()->numerify('0##-#######'),
            'is_active' => true,
        ];
    }
}
