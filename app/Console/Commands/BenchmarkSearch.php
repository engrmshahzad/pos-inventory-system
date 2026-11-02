<?php

namespace App\Console\Commands;

use App\Http\Controllers\Sales\PosController;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Times the real POS search/scan code with N extra products. Everything runs in a transaction that is rolled back,
 * so the database is left exactly as it was.
 */
class BenchmarkSearch extends Command
{
    protected $signature = 'pos:benchmark-search {--count=10000 : Extra products to generate} {--runs=20}';

    protected $description = 'Measure POS product search speed with many products (data is rolled back)';

    public function handle(): int
    {
        $count = (int) $this->option('count');
        $runs = max(5, (int) $this->option('runs'));
        $cashier = User::role('cashier')->whereNotNull('branch_id')->first() ?? throw new \RuntimeException('Seed the database first.');

        DB::beginTransaction();
        try {
            $this->info("Generating {$count} products…");
            $unitId = Unit::value('id');
            $branchIds = Branch::pluck('id');
            $now = now();
            $words = ['Tea', 'Rice', 'Soap', 'Milk', 'Oil', 'Cable', 'Charger', 'Biscuit', 'Juice', 'Masala', 'Shampoo', 'Sugar'];

            foreach (array_chunk(range(1, $count), 1000) as $chunk) {
                $rows = array_map(fn ($i) => [
                    'name' => $words[$i % 12].' Bench '.$i, 'sku' => 'BENCH-'.$i, 'barcode' => '99'.str_pad((string) $i, 11, '0', STR_PAD_LEFT),
                    'unit_id' => $unitId, 'purchase_price' => 100, 'sale_price' => 120, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ], $chunk);
                Product::insert($rows);
            }
            $ids = Product::where('sku', 'like', 'BENCH-%')->pluck('id');
            foreach ($ids->chunk(2000) as $chunk) {
                $stock = [];
                foreach ($chunk as $id) {
                    foreach ($branchIds as $b) {
                        $stock[] = ['branch_id' => $b, 'product_id' => $id, 'quantity' => 25, 'created_at' => $now, 'updated_at' => $now];
                    }
                }
                DB::table('branch_stock')->insert($stock);
            }
            $this->line('Products in database: '.Product::count());

            Auth::setUser($cashier);
            $pos = app(PosController::class);
            $cases = [
                'Grid page (no search)' => fn () => $pos->products(Request::create('/pos/products')),
                'Name search "masala"' => fn () => $pos->products(Request::create('/pos/products', 'GET', ['q' => 'masala', 'per_page' => 12])),
                'Name search "bench 77"' => fn () => $pos->products(Request::create('/pos/products', 'GET', ['q' => 'bench 77', 'per_page' => 12])),
                'SKU prefix "BENCH-99"' => fn () => $pos->products(Request::create('/pos/products', 'GET', ['q' => 'BENCH-99', 'per_page' => 12])),
                'Barcode scan (exact)' => fn () => $pos->lookup(Request::create('/pos/lookup', 'GET', ['code' => '9900000007777'])),
            ];

            $rows = [];
            foreach ($cases as $label => $fn) {
                $fn(); // warm up
                $times = [];
                foreach (range(1, $runs) as $_) {
                    $t = hrtime(true);
                    $fn();
                    $times[] = (hrtime(true) - $t) / 1e6;
                }
                sort($times);
                $median = $times[intdiv(count($times), 2)];
                $p95 = $times[(int) floor(count($times) * 0.95) - 1];
                $rows[] = [$label, number_format($median, 1).' ms', number_format($p95, 1).' ms', $p95 < 200 ? 'OK' : 'SLOW'];
            }

            $this->table(['Query', 'Median', 'p95', '< 200 ms'], $rows);
        } finally {
            DB::rollBack();
            $this->line('Rolled back — no data was kept. Products now: '.Product::count());
        }

        return self::SUCCESS;
    }
}
