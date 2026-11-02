<?php

namespace App\Http\Controllers\Catalogue;

use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogue\ProductRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    public function __construct(protected ProductService $service)
    {
    }

    public function index(Request $request): View
    {
        $branchId = currentBranchId();
        $perPage = in_array((int) $request->input('per_page'), [12, 24, 48, 96], true) ? (int) $request->input('per_page') : 12;

        $products = $this->filteredQuery($request, $branchId)
            ->with(['category:id,name,color,parent_id', 'brand:id,name', 'unit:id,short_name,allow_decimal'])
            ->paginate($perPage)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'branchCount' => $branchId ? 1 : max(Branch::count(), 1),
            'categories' => $this->categoryOptions(),
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
            'stats' => [
                'total' => Product::count(),
                'active' => Product::active()->count(),
                'low' => Product::stockStatus(Product::STATUS_LOW, $branchId)->count(),
                'out' => Product::stockStatus(Product::STATUS_OUT, $branchId)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('products.form', $this->formData(new Product([
            'is_active' => true, 'tax_percent' => (float) settings('tax_default_rate', 0), 'min_stock' => 5,
            'unit_id' => Unit::where('short_name', 'pcs')->value('id'),
        ])));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->service->create($request->productData(), $request->file('image'), $request->validated('opening_stock') ?? []);

        $redirect = $request->input('after_save') === 'new' ? route('products.create') : route('products.show', $product);

        return redirect($redirect)->with('success', "Product \"{$product->name}\" created.");
    }

    public function show(Request $request, Product $product): View
    {
        $product->load(['category.parent', 'brand', 'unit']);
        $actor = $request->user();
        $branchId = currentBranchId();

        // Owners see every branch; managers only their own.
        $stockRows = $product->stocks()
            ->with('branch:id,name,code,city,is_active')
            ->whereHas('branch')
            ->when(! $actor->isOwner(), fn ($q) => $q->where('branch_id', $actor->branch_id))
            ->get()
            ->sortBy('branch.name');

        $movements = StockMovement::with(['branch:id,name,code', 'user:id,name'])
            ->where('product_id', $product->id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest('id')
            ->paginate(15, ['*'], 'history_page')
            ->withQueryString();

        return view('products.show', [
            'product' => $product,
            'stockRows' => $stockRows,
            'movements' => $movements,
            'totalQty' => (float) $stockRows->sum('quantity'),
            'tab' => $request->input('tab', $request->has('history_page') ? 'history' : 'overview'),
        ]);
    }

    public function edit(Product $product): View
    {
        return view('products.form', $this->formData($product));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->service->update($product, $request->productData(), $request->file('image'), $request->boolean('remove_image'));

        return redirect()->route('products.show', $product)->with('success', "Product \"{$product->name}\" updated.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->service->delete($product);

        return redirect()->route('products.index')->with('success', "Product \"{$product->name}\" deleted.");
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete', 'barcodes'])],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Select at least one product.']);

        if ($data['action'] === 'barcodes') {
            return redirect()->route('barcodes.index', ['ids' => $data['ids']]);
        }

        $permission = $data['action'] === 'delete' ? 'products.delete' : 'products.edit';
        abort_unless($request->user()->can($permission), 403);

        $products = Product::whereIn('id', $data['ids'])->get();

        foreach ($products as $product) {
            match ($data['action']) {
                'activate' => $product->update(['is_active' => true]),
                'deactivate' => $product->update(['is_active' => false]),
                'delete' => $this->service->delete($product),
            };
        }

        $verb = ['activate' => 'activated', 'deactivate' => 'deactivated', 'delete' => 'deleted'][$data['action']];

        return back()->with('success', "{$products->count()} product(s) {$verb}.");
    }

    /** JSON search used by Tom Select (barcodes, filters) and later by the POS. */
    public function search(Request $request): JsonResponse
    {
        // Owner may ask for any branch's stock (e.g. the sending branch of a transfer); others always get their own.
        $branchId = $request->user()->isOwner() && $request->filled('branch') ? $request->integer('branch') : currentBranchId();
        $term = trim((string) $request->input('q'));

        $products = Product::query()
            ->select(['products.id', 'products.name', 'products.sku', 'products.barcode', 'products.sale_price', 'products.purchase_price', 'products.tax_percent', 'products.track_expiry', 'products.unit_id'])
            ->withStock($branchId)
            ->with('unit:id,short_name,allow_decimal')
            ->when($request->boolean('active_only', true), fn ($q) => $q->active())
            ->when($term !== '', fn (Builder $q) => $q->where(fn ($q) => $q->where('barcode', $term)->orWhere('sku', $term)
                ->orWhere('name', 'like', "%{$term}%")->orWhere('sku', 'like', "{$term}%")))
            ->orderByRaw('barcode = ? DESC', [$term])
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'price' => (float) $p->sale_price,
                'price_label' => money($p->sale_price),
                'stock' => (float) $p->stock_qty,
                'unit' => $p->unit?->short_name,
                'decimal' => (bool) $p->unit?->allow_decimal,
                'cost' => (float) $p->purchase_price,
                'tax_percent' => (float) $p->tax_percent,
                'track_expiry' => (bool) $p->track_expiry,
            ]);

        return response()->json($products);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $branchId = currentBranchId();
        $label = $branchId ? currentBranch()?->code : 'all-branches';

        return Excel::download(
            new ProductsExport($this->filteredQuery($request, $branchId), $branchId),
            'products-'.strtolower($label).'-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    protected function filteredQuery(Request $request, ?int $branchId): Builder
    {
        $sort = $request->input('sort', 'name');

        return Product::query()
            ->withStock($branchId)
            ->search($request->input('search'))
            ->when($request->filled('category'), function (Builder $q) use ($request) {
                $id = $request->integer('category');
                $q->where(fn ($q) => $q->where('category_id', $id)->orWhereIn('category_id', Category::where('parent_id', $id)->select('id')));
            })
            ->when($request->filled('brand'), fn (Builder $q) => $q->where('brand_id', $request->integer('brand')))
            ->when($request->filled('stock'), fn (Builder $q) => $q->stockStatus($request->input('stock'), $branchId))
            ->when($request->input('status') === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->when($sort === 'price', fn ($q) => $q->orderBy('sale_price'))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('sale_price'))
            ->when($sort === 'stock', fn ($q) => $q->orderBy('stock_qty'))
            ->when($sort === 'latest', fn ($q) => $q->latest('id'))
            ->orderBy('name');
    }

    protected function formData(Product $product): array
    {
        $actor = auth()->user();

        return [
            'product' => $product,
            'categories' => $this->categoryOptions(),
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
            'units' => Unit::orderBy('name')->get(),
            'branches' => $actor->isOwner()
                ? Branch::active()->orderBy('name')->get()
                : Branch::whereKey($actor->branch_id)->get(),
        ];
    }

    /** @return array<int, string> "Parent › Child" labels, parents first. */
    protected function categoryOptions(): array
    {
        $options = [];
        Category::roots()->with(['children' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get()
            ->each(function (Category $root) use (&$options) {
                $options[$root->id] = $root->name;
                foreach ($root->children as $child) {
                    $options[$child->id] = "{$root->name} › {$child->name}";
                }
            });

        return $options;
    }
}
