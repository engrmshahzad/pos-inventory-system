<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/** Restocking purchases over the last few weeks, made through PurchaseService. */
class PurchaseSeeder extends Seeder
{
    public function run(PurchaseService $purchases): void
    {
        if (Purchase::exists()) {
            return;
        }

        mt_srand(7);
        $suppliers = Supplier::all();
        $products = Product::with('unit')->get();

        foreach (Branch::orderBy('id')->get() as $branch) {
            $manager = User::role('manager')->where('branch_id', $branch->id)->first();
            Auth::setUser($manager);

            foreach ([20, 14, 9, 3] as $i => $daysAgo) {
                $supplier = $suppliers[($branch->id * 3 + $i) % $suppliers->count()];
                $items = $products->random(mt_rand(3, 6))->map(fn ($p) => [
                    'product_id' => $p->id,
                    'quantity' => $p->unit?->allow_decimal ? mt_rand(10, 40) : mt_rand(6, 24),
                    'unit_cost' => (float) $p->purchase_price,
                    'expiry_date' => $p->track_expiry ? now()->addDays(mt_rand(15, 300))->toDateString() : null,
                    'batch_no' => $p->track_expiry ? 'B'.mt_rand(1000, 9999) : null,
                ])->values()->all();

                $total = $purchases->calculate(['items' => $items])['grand_total'];
                $paid = [$total, round($total / 2), 0, $total][$i];

                $purchase = $purchases->create($manager, $branch, [
                    'supplier_id' => $supplier->id,
                    'reference_no' => 'INV-'.mt_rand(10000, 99999),
                    'purchase_date' => today()->subDays($daysAgo)->toDateString(),
                    'status' => 'received',
                    'items' => $items,
                    'paid_amount' => $paid,
                    'payment_method' => $i % 2 ? 'bank' : 'cash',
                ]);
                $at = Carbon::today()->subDays($daysAgo)->setTime(11, 0);
                $purchase->update(['received_at' => $at, 'created_at' => $at]);
                $purchase->payments()->update(['paid_at' => $at, 'created_at' => $at]);
            }

            // One open order per branch
            $purchases->create($manager, $branch, [
                'supplier_id' => $suppliers[$branch->id % $suppliers->count()]->id,
                'purchase_date' => today()->toDateString(),
                'status' => 'ordered',
                'items' => $products->random(3)->map(fn ($p) => ['product_id' => $p->id, 'quantity' => 12, 'unit_cost' => (float) $p->purchase_price])->values()->all(),
            ]);
        }

        Auth::logout();
    }
}
