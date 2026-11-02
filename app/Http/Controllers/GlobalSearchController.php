<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ctrl+K: products, customers, suppliers, invoices and purchases the user may see. */
class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q'));
        $user = $request->user();

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $results = collect();

        if ($user->can('products.view')) {
            Product::search($q)->limit(5)->get(['id', 'name', 'sku', 'sale_price'])->each(fn ($p) => $results->push([
                'group' => 'Products', 'label' => $p->name, 'sub' => $p->sku.' · '.money($p->sale_price), 'url' => route('products.show', $p), 'icon' => 'package',
            ]));
        }
        if ($user->can('customers.view')) {
            Customer::search($q)->limit(5)->get(['id', 'name', 'phone', 'balance'])->each(fn ($c) => $results->push([
                'group' => 'Customers', 'label' => $c->name, 'sub' => $c->phone.((float) $c->balance > 0 ? ' · due '.money($c->balance) : ''), 'url' => route('customers.show', $c), 'icon' => 'users',
            ]));
        }
        if ($user->can('suppliers.view')) {
            Supplier::search($q)->limit(3)->get(['id', 'name', 'company'])->each(fn ($s) => $results->push([
                'group' => 'Suppliers', 'label' => $s->name, 'sub' => (string) $s->company, 'url' => route('suppliers.show', $s), 'icon' => 'factory',
            ]));
        }
        if ($user->can('sales.view')) {
            Sale::query()->whereIn('sales.status', ['completed', 'cancelled'])->visibleTo($user)->where('invoice_no', 'like', '%'.strtoupper($q).'%')
                ->latest('sold_at')->limit(5)->get(['id', 'invoice_no', 'grand_total', 'sold_at', 'status'])
                ->each(fn ($s) => $results->push([
                    'group' => 'Invoices', 'label' => $s->invoice_no, 'sub' => fmtDate($s->sold_at).' · '.money($s->grand_total).($s->status === 'cancelled' ? ' · cancelled' : ''), 'url' => route('sales.show', $s), 'icon' => 'receipt',
                ]));
        }
        if ($user->can('purchases.view')) {
            Purchase::visibleTo($user)->where(fn ($w) => $w->where('purchase_no', 'like', '%'.strtoupper($q).'%')->orWhere('reference_no', 'like', "%{$q}%"))
                ->latest('id')->limit(3)->get(['id', 'purchase_no', 'grand_total'])
                ->each(fn ($p) => $results->push(['group' => 'Purchases', 'label' => $p->purchase_no, 'sub' => money($p->grand_total), 'url' => route('purchases.show', $p), 'icon' => 'truck']));
        }

        return response()->json($results->values());
    }
}
