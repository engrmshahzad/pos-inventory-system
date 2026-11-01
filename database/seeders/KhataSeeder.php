<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PurchaseReturnService;
use App\Services\SaleReturnService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/** Demo khata activity: customer receipts, a supplier payment and one return of each kind. */
class KhataSeeder extends Seeder
{
    public function run(PaymentService $payments, SaleReturnService $saleReturns, PurchaseReturnService $purchaseReturns): void
    {
        $hyd = Branch::where('code', 'HYD')->first();
        $manager = User::role('manager')->where('branch_id', $hyd->id)->first();
        Auth::setUser($manager);

        foreach (Customer::where('balance', '>', 1000)->orderByDesc('balance')->limit(3)->get() as $customer) {
            $payments->receiveFromCustomer($manager, $hyd, $customer, ['amount' => round((float) $customer->balance / 2), 'method' => 'cash', 'note' => 'Part payment']);
        }

        if ($supplier = Supplier::where('balance', '>', 0)->orderByDesc('balance')->first()) {
            $payments->payToSupplier($manager, $hyd, $supplier, ['amount' => min(15000, (float) $supplier->balance), 'method' => 'bank', 'reference' => 'CHQ-'.mt_rand(100000, 999999)]);
        }

        if ($sale = Sale::completed()->where('branch_id', $hyd->id)->whereNotNull('customer_id')->with('items')->latest('id')->first()) {
            $item = $sale->items->first();
            $saleReturns->create($manager, $sale, [$item->id => 1], 'adjust', 'Packet damaged');
        }

        if ($purchase = Purchase::received()->where('branch_id', $hyd->id)->with('items')->latest('id')->first()) {
            $item = $purchase->items->first();
            $purchaseReturns->create($manager, $purchase, [$item->id => 2], 'adjust', 'Near expiry');
        }

        Auth::logout();
    }
}
