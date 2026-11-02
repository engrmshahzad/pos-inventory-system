<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\ProductService;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(ProductService $service): void
    {
        if (Product::exists()) {
            return;
        }

        // name, category, brand, unit, purchase, sale, wholesale, min_stock, base opening qty, track_expiry
        $products = [
            ['Tapal Danedar 950g', 'Tea', 'Tapal', 'pcs', 1480, 1650, 1600, 10, 40, true],
            ['Tapal Family Mixture 190g', 'Tea', 'Tapal', 'pcs', 330, 380, 360, 15, 60, true],
            ['Lipton Yellow Label 475g', 'Tea', 'Lipton', 'pcs', 1050, 1180, 1140, 10, 30, true],
            ['Coca-Cola 1.5 Litre', 'Soft Drinks', 'Coca-Cola', 'pcs', 165, 200, 185, 24, 96, true],
            ['Sprite 500ml', 'Soft Drinks', 'Coca-Cola', 'pcs', 85, 100, 95, 24, 120, true],
            ['Nestlé Pure Life Water 1.5L', 'Beverages', 'Nestlé', 'pcs', 75, 95, 85, 30, 150, false],
            ['Nestlé Nesfruta Mango 1L', 'Beverages', 'Nestlé', 'pcs', 290, 340, 320, 12, 48, true],
            ['Olpers Full Cream Milk 1L', 'Dairy', 'Olpers', 'pcs', 290, 330, 315, 24, 80, true],
            ['Olpers Cream 200ml', 'Dairy', 'Olpers', 'pcs', 230, 270, 255, 12, 36, true],
            ['Nestlé Milkpak 1L', 'Dairy', 'Nestlé', 'pcs', 295, 340, 325, 24, 72, true],
            ['Nestlé Everyday Milk Powder 600g', 'Dairy', 'Nestlé', 'pcs', 1150, 1290, 1240, 8, 30, true],
            ['Basmati Rice Super Kernel', 'Rice & Flour', null, 'kg', 330, 380, 360, 25, 120, false],
            ['Sella Rice 1121', 'Rice & Flour', null, 'kg', 360, 410, 390, 25, 100, false],
            ['Chakki Atta', 'Rice & Flour', null, 'kg', 115, 135, 125, 50, 200, false],
            ['White Sugar', 'Grocery', null, 'kg', 145, 165, 155, 50, 180, false],
            ['Daal Chana', 'Grocery', null, 'kg', 280, 320, 300, 20, 60, false],
            ['Daal Masoor', 'Grocery', null, 'kg', 300, 350, 330, 20, 50, false],
            ['Shan Biryani Masala 50g', 'Spices & Masala', 'Shan', 'pcs', 125, 150, 140, 20, 80, true],
            ['Shan Nihari Masala 60g', 'Spices & Masala', 'Shan', 'pcs', 125, 150, 140, 15, 50, true],
            ['National Red Chilli Powder 200g', 'Spices & Masala', 'National', 'pcs', 290, 340, 320, 12, 40, true],
            ['National Salt 800g', 'Spices & Masala', 'National', 'pcs', 55, 70, 65, 30, 100, false],
            ['National Ketchup 800g', 'Grocery', 'National', 'pcs', 390, 450, 430, 10, 30, true],
            ['Dalda Cooking Oil 5L', 'Cooking Oil', 'Dalda', 'pcs', 2850, 3100, 3000, 8, 25, true],
            ['Dalda Banaspati Ghee 1kg', 'Cooking Oil', 'Dalda', 'pcs', 560, 620, 600, 12, 40, true],
            ['Sufi Canola Oil 1L', 'Cooking Oil', null, 'pcs', 540, 600, 580, 12, 36, true],
            ['Peek Freans Sooper 16 Pack', 'Snacks & Biscuits', 'Peek Freans', 'pack', 300, 350, 330, 15, 50, true],
            ['Peek Freans Rio Chocolate', 'Snacks & Biscuits', 'Peek Freans', 'pcs', 45, 60, 55, 30, 120, true],
            ['Lays Masala 70g', 'Snacks & Biscuits', null, 'pcs', 90, 110, 100, 24, 96, true],
            ['Kurkure Chutney Chaska', 'Snacks & Biscuits', null, 'pcs', 45, 60, 55, 24, 100, true],
            ['Lifebuoy Soap 100g', 'Soap & Shampoo', 'Unilever', 'pcs', 115, 140, 130, 24, 100, false],
            ['Lux Soap Rose 150g', 'Soap & Shampoo', 'Unilever', 'pcs', 175, 210, 195, 20, 80, false],
            ['Sunsilk Shampoo 360ml', 'Soap & Shampoo', 'Unilever', 'pcs', 720, 820, 790, 8, 30, false],
            ['Head & Shoulders 360ml', 'Soap & Shampoo', null, 'pcs', 1050, 1200, 1150, 6, 20, false],
            ['Colgate MaxFresh 150g', 'Oral Care', 'Colgate', 'pcs', 330, 390, 370, 12, 50, false],
            ['Colgate Toothbrush Extra Clean', 'Oral Care', 'Colgate', 'pcs', 120, 150, 140, 15, 60, false],
            ['Surf Excel 1kg', 'Household', 'Unilever', 'pcs', 720, 820, 790, 10, 40, false],
            ['Vim Dishwash Bar', 'Household', 'Unilever', 'pcs', 70, 90, 80, 24, 100, false],
            ['Harpic Toilet Cleaner 500ml', 'Household', null, 'pcs', 390, 450, 430, 10, 30, false],
            ['Mortein Insect Spray 375ml', 'Household', null, 'pcs', 780, 890, 850, 6, 18, false],
            ['Eggs (Farm)', 'Grocery', null, 'dozen', 330, 380, 360, 10, 40, true],
            ['Audionic Earphones E-100', 'Mobile Accessories', 'Audionic', 'pcs', 650, 950, 850, 5, 20, false],
            ['Audionic Bluetooth Speaker', 'Mobile Accessories', 'Audionic', 'pcs', 3200, 4200, 3900, 3, 8, false],
            ['Fast Charger 20W Type-C', 'Mobile Accessories', null, 'pcs', 900, 1400, 1250, 5, 15, false],
            ['USB-C Data Cable 1m', 'Mobile Accessories', null, 'pcs', 180, 350, 300, 10, 40, false],
            ['Power Bank 10000mAh', 'Mobile Accessories', 'Audionic', 'pcs', 2600, 3500, 3250, 3, 10, false],
            ['Screen Protector (Universal)', 'Mobile Accessories', null, 'pcs', 60, 200, 150, 20, 60, false],
            ['Pampers Baby Dry Size 4 (36)', 'Baby Care', 'Pampers', 'pack', 2900, 3250, 3150, 5, 15, true],
            ['Guard Baby Wipes 72s', 'Baby Care', 'Guard', 'pack', 290, 350, 330, 10, 30, true],
            ['Cerelac Wheat 175g', 'Baby Care', 'Nestlé', 'pcs', 450, 520, 500, 8, 24, true],
            ['Nido Fortified 390g', 'Baby Care', 'Nestlé', 'pcs', 1350, 1500, 1450, 6, 20, true],
        ];

        $categories = Category::pluck('id', 'name');
        $brands = Brand::pluck('id', 'name');
        $units = Unit::pluck('id', 'short_name');
        $branches = Branch::orderBy('id')->pluck('id')->all();
        // Branch multipliers make each branch look different; a few items are left low/out on purpose.
        $multipliers = [1.0, 1.4, 0.6];

        foreach ($products as $i => [$name, $category, $brand, $unit, $cost, $price, $wholesale, $min, $qty, $expiry]) {
            $opening = [];
            foreach ($branches as $b => $branchId) {
                $amount = (int) round($qty * 3 * ($multipliers[$b] ?? 1)); // enough for ~60 days of demo sales
                if ($i % 11 === 3 && $b === 2) {
                    $amount = 0;                       // out of stock in Sukkur
                } elseif ($i % 7 === 5) {
                    $amount = max(1, (int) floor($min / 2)); // low stock everywhere
                }
                $opening[$branchId] = $amount;
            }

            $service->create([
                'name' => $name,
                'category_id' => $categories[$category] ?? null,
                'brand_id' => $brand ? ($brands[$brand] ?? null) : null,
                'unit_id' => $units[$unit],
                'purchase_price' => $cost,
                'sale_price' => $price,
                'wholesale_price' => $wholesale,
                'tax_percent' => 0,
                'min_stock' => $min,
                'track_expiry' => $expiry,
                'is_active' => true,
            ], null, $opening);
        }
    }
}
