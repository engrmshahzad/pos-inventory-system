<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\AdjustmentService;
use App\Services\StockCountService;
use App\Services\TransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/** Transfers in every status, adjustments (one pending), a finished stock count and some expiring batches. */
class InventoryControlSeeder extends Seeder
{
    public function run(TransferService $transfers, AdjustmentService $adjustments, StockCountService $counts): void
    {
        if (StockTransfer::exists()) {
            return;
        }

        [$hyd, $khi, $suk] = [Branch::where('code', 'HYD')->first(), Branch::where('code', 'KHI')->first(), Branch::where('code', 'SUK')->first()];
        $owner = User::role('owner')->first();
        $managers = User::role('manager')->get()->keyBy('branch_id');
        $as = function (User $u) { Auth::setUser($u); return $u; };

        // Products HYD has plenty of
        $plenty = fn (Branch $b, int $n) => Product::whereIn('id', BranchStock::where('branch_id', $b->id)->where('quantity', '>', 30)->pluck('product_id'))->take($n)->get();
        $items = fn ($products, $qty) => $products->map(fn ($p) => ['product_id' => $p->id, 'quantity' => $qty])->all();

        // 1. Received in full (HYD → SUK)
        $t = $transfers->create($as($managers[$hyd->id]), ['to_branch_id' => $suk->id, 'transfer_date' => today()->subDays(6), 'items' => $items($plenty($hyd, 3), 5), 'note' => 'Weekly top-up'], true);
        $transfers->receive($t, $as($managers[$suk->id]), []);

        // 2. Partially received with a shortage (KHI → HYD)
        $t = $transfers->create($as($managers[$khi->id]), ['to_branch_id' => $hyd->id, 'transfer_date' => today()->subDays(3), 'items' => $items($plenty($khi, 2), 10)], true);
        $first = $t->items()->first();
        $transfers->receive($t, $as($managers[$hyd->id]), [$first->id => 8], '2 units damaged in transit');

        // 3. Sent, waiting at KHI
        $transfers->create($as($managers[$hyd->id]), ['to_branch_id' => $khi->id, 'transfer_date' => today(), 'items' => $items($plenty($hyd, 4), 6), 'note' => 'Weekend demand'], true);

        // 4. Owner request: SUK should send to KHI
        $transfers->create($as($owner), ['from_branch_id' => $suk->id, 'to_branch_id' => $khi->id, 'transfer_date' => today(), 'items' => $items($plenty($suk, 2), 4)]);

        // 5. Cancelled draft
        $t = $transfers->create($as($managers[$khi->id]), ['to_branch_id' => $suk->id, 'transfer_date' => today()->subDay(), 'items' => $items($plenty($khi, 1), 3)]);
        $transfers->cancel($t, $managers[$khi->id], 'Entered by mistake');

        // Adjustments: small one applied, big one pending owner approval
        $adjustments->create($as($managers[$hyd->id]), $hyd, ['reason' => 'damaged', 'note' => 'Rats in store room', 'items' => [['product_id' => $plenty($hyd, 1)->first()->id, 'quantity' => -2]]]);
        $expensive = Product::orderByDesc('purchase_price')->first();
        $adjustments->create($as($managers[$khi->id]), $khi, ['reason' => 'lost', 'note' => 'Missing after stock check', 'items' => [['product_id' => $expensive->id, 'quantity' => -2]]]);
        $adjustments->create($as($owner), $suk, ['reason' => 'found', 'items' => [['product_id' => $plenty($suk, 1)->first()->id, 'quantity' => 3]]]);

        // A finished stock count at SUK (one category)
        $categoryId = Product::whereNotNull('category_id')->value('category_id');
        $count = $counts->start($as($managers[$suk->id]), $suk, $categoryId, 'Month-end count');
        $values = $count->items->mapWithKeys(fn ($i, $k) => [$i->id => max(0, (float) $i->system_quantity - ($k === 0 ? 1 : 0))])->all();
        $counts->saveCounts($count, $values);
        $counts->apply($count, $managers[$suk->id]);

        // Make a few purchased batches expired / expiring for the Expiry page
        PurchaseItem::whereNotNull('expiry_date')->orderBy('id')->take(2)->get()->each->update(['expiry_date' => today()->subDays(5)]);
        PurchaseItem::whereNotNull('expiry_date')->orderBy('id')->skip(2)->take(3)->get()->each->update(['expiry_date' => today()->addDays(12)]);

        Auth::logout();
    }
}
