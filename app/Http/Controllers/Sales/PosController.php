<?php

namespace App\Http\Controllers\Sales;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CheckoutRequest;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(protected SaleService $sales)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $branch = currentBranch();

        if (! $branch) {
            return view('pos.choose-branch');
        }

        return view('pos.index', [
            'branch' => $branch,
            'register' => CashRegister::openFor($user, $branch->id),
            'categories' => Category::active()->roots()->orderBy('name')->get(['id', 'name', 'color', 'icon']),
            'heldCount' => $this->sales->heldQuery($user, $branch)->count(),
        ]);
    }

    /** Product grid / search for the POS (current branch stock). */
    public function products(Request $request): JsonResponse
    {
        $branch = $this->branchOrFail();
        $term = trim((string) $request->input('q'));

        $paginator = Product::query()
            ->active()
            ->withStock($branch->id)
            ->with(['unit:id,short_name,allow_decimal', 'category:id,color'])
            ->when($request->filled('category'), function ($q) use ($request) {
                $id = $request->integer('category');
                $q->where(fn ($q) => $q->where('category_id', $id)->orWhereIn('category_id', Category::where('parent_id', $id)->select('id')));
            })
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "{$term}%")->orWhere('barcode', 'like', "{$term}%")))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 40) ?: 40);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Product $p) => $this->productPayload($p)),
            'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
        ]);
    }

    /** Exact barcode / SKU match, used by the scanner. */
    public function lookup(Request $request): JsonResponse
    {
        $branch = $this->branchOrFail();
        $code = trim((string) $request->input('code'));

        $product = Product::active()->withStock($branch->id)
            ->with(['unit:id,short_name,allow_decimal', 'category:id,color'])
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', strtoupper($code)))
            ->first();

        return $product
            ? response()->json($this->productPayload($product))
            : response()->json(['message' => "No product with barcode \"{$code}\"."], 404);
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $branch = $this->branchOrFail();
        $register = CashRegister::openFor($user, $branch->id);

        if (! $register) {
            return response()->json(['message' => 'Open a shift before selling.'], 422);
        }

        try {
            $sale = $this->sales->complete($user, $branch, $register, $request->validated());
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'grand_total' => (float) $sale->grand_total,
            'paid' => (float) $sale->paid_amount,
            'due' => (float) $sale->due_amount,
            'change' => (float) $sale->change_amount,
            'customer' => $sale->customer?->name,
            'receipt_url' => route('sales.receipt', $sale),
            'show_url' => route('sales.show', $sale),
            'whatsapp_url' => $this->whatsappUrl($sale),
        ]);
    }

    public function hold(CheckoutRequest $request): JsonResponse
    {
        $sale = $this->sales->hold($request->user(), $this->branchOrFail(), $request->validated());

        return response()->json([
            'id' => $sale->id,
            'held_count' => $this->sales->heldQuery($request->user(), $this->branchOrFail())->count(),
        ]);
    }

    public function held(Request $request): JsonResponse
    {
        $held = $this->sales->heldQuery($request->user(), $this->branchOrFail())
            ->with(['customer:id,name', 'user:id,name'])
            ->withCount('items')
            ->latest('id')
            ->get()
            ->map(fn (Sale $s) => [
                'id' => $s->id,
                'customer' => $s->customer?->name ?? 'Walk-in Customer',
                'items' => $s->items_count,
                'total' => (float) $s->grand_total,
                'total_label' => money($s->grand_total),
                'user' => $s->user?->name,
                'time' => $s->created_at->diffForHumans(),
                'note' => $s->note,
            ]);

        return response()->json($held);
    }

    /** Cart payload to resume a held bill. */
    public function resume(Request $request, Sale $sale): JsonResponse
    {
        $sale = $this->sales->heldQuery($request->user(), $this->branchOrFail())->with(['items', 'customer'])->findOrFail($sale->id);
        $products = Product::withStock($sale->branch_id)->with(['unit:id,short_name,allow_decimal', 'category:id,color'])
            ->whereIn('id', $sale->items->pluck('product_id'))->get()->keyBy('id');

        return response()->json([
            'hold_id' => $sale->id,
            'customer' => $sale->customer ? $this->customerPayload($sale->customer) : null,
            'discount_type' => $sale->discount_type,
            'discount_value' => (float) $sale->discount_value,
            'shipping' => (float) $sale->shipping,
            'note' => $sale->note,
            'items' => $sale->items->filter(fn ($i) => $products->has($i->product_id))->map(fn ($i) => array_merge(
                $this->productPayload($products[$i->product_id]),
                ['qty' => (float) $i->quantity, 'price' => (float) $i->unit_price, 'discount' => (float) $i->discount],
            ))->values(),
        ]);
    }

    public function destroyHeld(Request $request, Sale $sale): JsonResponse
    {
        $this->sales->heldQuery($request->user(), $this->branchOrFail())->findOrFail($sale->id)->delete();

        return response()->json(['held_count' => $this->sales->heldQuery($request->user(), $this->branchOrFail())->count()]);
    }

    public function customers(Request $request): JsonResponse
    {
        $customers = Customer::active()->search($request->input('q'))->orderBy('name')->limit(20)->get();

        return response()->json($customers->map(fn ($c) => $this->customerPayload($c)));
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('customers.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s]{7,20}$/', Rule::unique('customers', 'phone')],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
        ], ['phone.unique' => 'A customer with this phone number already exists.', 'phone.regex' => 'Enter a valid phone number.']);

        $customer = Customer::create($data + ['is_active' => true]);

        return response()->json($this->customerPayload($customer), 201);
    }

    protected function productPayload(Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'barcode' => $p->barcode,
            'price' => (float) $p->sale_price,
            'tax_percent' => (float) $p->tax_percent,
            'stock' => (float) $p->stock_qty,
            'unit' => $p->unit?->short_name,
            'decimal' => (bool) $p->unit?->allow_decimal,
            'image' => $p->image_url,
            'color' => $p->category?->color ?? '#4F46E5',
        ];
    }

    protected function customerPayload(Customer $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'balance' => (float) $c->balance,
            'balance_label' => money($c->balance),
            'credit_limit' => (float) $c->credit_limit,
        ];
    }

    protected function whatsappUrl(Sale $sale): string
    {
        $lines = [
            '*'.settings('business_name', config('app.name')).'* — '.$sale->branch->name,
            "Invoice: {$sale->invoice_no}",
            'Date: '.$sale->sold_at->format('d M Y, h:i A'),
            '',
        ];
        foreach ($sale->items as $item) {
            $lines[] = "{$item->product_name} × ".rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.').' = '.money($item->line_total);
        }
        $lines[] = '';
        $lines[] = '*Total: '.money($sale->grand_total).'*';
        $lines[] = 'Paid: '.money($sale->paid_amount);
        if ((float) $sale->due_amount > 0) {
            $lines[] = 'Due: '.money($sale->due_amount);
        }
        $lines[] = '';
        $lines[] = 'Thank you for shopping with us!';

        $phone = $sale->customer?->whatsapp_number ?? '';

        return 'https://wa.me/'.$phone.'?text='.rawurlencode(implode("\n", $lines));
    }

    protected function branchOrFail()
    {
        return currentBranch() ?? abort(422, 'Choose a branch from the branch switcher before using the POS.');
    }
}
