<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['Pieces', 'pcs', false], ['Kilogram', 'kg', true], ['Litre', 'ltr', true],
            ['Dozen', 'dozen', false], ['Box', 'box', false], ['Pack', 'pack', false],
        ];
        foreach ($units as [$name, $short, $decimal]) {
            Unit::firstOrCreate(['short_name' => $short], ['name' => $name, 'allow_decimal' => $decimal]);
        }

        $categories = [
            ['Grocery', 'shopping-cart', '#10B981', ['Rice & Flour', 'Spices & Masala', 'Cooking Oil']],
            ['Beverages', 'zap', '#0EA5E9', ['Tea', 'Soft Drinks']],
            ['Dairy', 'package', '#F59E0B', []],
            ['Snacks & Biscuits', 'sparkles', '#EC4899', []],
            ['Personal Care', 'award', '#8B5CF6', ['Soap & Shampoo', 'Oral Care']],
            ['Household', 'store', '#14B8A6', []],
            ['Mobile Accessories', 'phone', '#4F46E5', []],
            ['Baby Care', 'sun', '#EF4444', []],
        ];
        foreach ($categories as [$name, $icon, $color, $children]) {
            $parent = Category::firstOrCreate(['name' => $name, 'parent_id' => null], ['icon' => $icon, 'color' => $color, 'is_active' => true]);
            foreach ($children as $child) {
                Category::firstOrCreate(['name' => $child, 'parent_id' => $parent->id], ['icon' => $icon, 'color' => $color, 'is_active' => true]);
            }
        }

        foreach (['Tapal', 'Lipton', 'Nestlé', 'Olpers', 'Shan', 'National', 'Dalda', 'Unilever', 'Peek Freans', 'Audionic', 'Guard', 'Pampers', 'Coca-Cola', 'Colgate'] as $brand) {
            Brand::firstOrCreate(['name' => $brand]);
        }
    }
}
