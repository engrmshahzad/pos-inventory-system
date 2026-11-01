<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Note: factories do not create branch_stock rows; use ProductService::create() for that.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $cost = fake()->numberBetween(50, 2000);

        return [
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'sku' => fake()->unique()->bothify('SKU-#####'),
            'barcode' => fake()->unique()->ean13(),
            'unit_id' => Unit::factory(),
            'purchase_price' => $cost,
            'sale_price' => round($cost * 1.2),
            'tax_percent' => 0,
            'min_stock' => 5,
            'is_active' => true,
        ];
    }
}
