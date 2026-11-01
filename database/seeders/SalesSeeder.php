<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/** A few days of realistic sales per branch, made through SaleService so stock and dues stay consistent. */
class SalesSeeder extends Seeder
{
    public function run(SaleService $sales, CashRegisterService $registers): void
    {
        if (Sale::exists()) {
            return;
        }

        mt_srand(42);
        $products = Product::active()->get(['id', 'sale_price', 'unit_id'])->load('unit');
        $customers = Customer::pluck('id')->all();

        foreach (Branch::orderBy('id')->get() as $branch) {
            $cashier = User::role('cashier')->where('branch_id', $branch->id)->first();
            if (! $cashier) {
                continue;
            }
            Auth::setUser($cashier);

            foreach (range(6, 0) as $daysAgo) {
                $day = Carbon::today()->subDays($daysAgo);
                $register = $registers->open($cashier, $branch->id, 5000);
                $register->update(['opened_at' => $day->copy()->setTime(9, 0)]);

                foreach (range(1, mt_rand(4, 7)) as $n) {
                    $items = $products->random(mt_rand(1, 4))->map(fn ($p) => [
                        'product_id' => $p->id,
                        'quantity' => $p->unit?->allow_decimal ? mt_rand(1, 5) / 2 + 0.5 : mt_rand(1, 3),
                    ])->values()->all();

                    $credit = $n % 4 === 0;
                    $customerId = $credit || $n % 3 === 0 ? $customers[array_rand($customers)] : null;
                    $total = $sales->calculate($cashier, ['items' => $items])['grand_total'];
                    $payments = $credit
                        ? [['method' => 'cash', 'amount' => round($total / 2)]]
                        : [['method' => ['cash', 'cash', 'jazzcash', 'card', 'easypaisa'][$n % 5], 'amount' => $total]];

                    try {
                        $sale = $sales->complete($cashier, $branch, $register, [
                            'customer_id' => $customerId,
                            'items' => $items,
                            'payments' => $payments,
                        ]);
                    } catch (\Throwable) {
                        continue; // e.g. an item ran out in this branch
                    }

                    $at = $day->copy()->setTime(8 + $n * 2, mt_rand(0, 59)); // later invoices get later times
                    $sale->update(['sold_at' => $at, 'created_at' => $at]);
                    $sale->payments()->update(['paid_at' => $at, 'created_at' => $at]);
                }

                // Keep today's shift open so the POS is ready to use.
                if ($daysAgo > 0) {
                    $summary = $registers->summary($register);
                    $registers->close($register, $summary['expected_cash'] - ($daysAgo === 3 ? 150 : 0), $daysAgo === 3 ? 'Short by Rs 150' : null);
                    $register->update(['closed_at' => $day->copy()->setTime(22, 0)]);
                }
            }
        }

        Auth::logout();
    }
}
