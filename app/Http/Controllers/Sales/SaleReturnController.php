<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\BarcodeService;
use App\Services\SaleReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaleReturnController extends Controller
{
    public function __construct(protected SaleReturnService $service)
    {
    }

    public function index(Request $request): View
    {
        $query = SaleReturn::visibleTo($request->user())
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('return_no', 'like', '%'.$request->input('search').'%')
                ->orWhereHas('sale', fn ($s) => $s->where('invoice_no', 'like', '%'.$request->input('search').'%'))));

        return view('sale-returns.index', [
            'returns' => (clone $query)->with(['sale:id,invoice_no', 'customer:id,name', 'branch:id,code', 'user:id,name'])->withCount('items')
                ->latest('returned_at')->paginate(25)->withQueryString(),
            'summary' => (clone $query)->selectRaw("COUNT(*) as count, COALESCE(SUM(total),0) as total,
                COALESCE(SUM(CASE WHEN refund_method = 'adjust' THEN total ELSE 0 END),0) as adjusted")->first(),
            'today' => (float) (clone $query)->where('returned_at', '>=', today())->sum('total'),
        ]);
    }

    /** Search by invoice number (typed or scanned from the receipt barcode), then show returnable lines. */
    public function create(Request $request): View|RedirectResponse
    {
        $sale = null;

        if ($request->filled('invoice')) {
            $term = strtoupper(trim($request->input('invoice')));
            $sale = Sale::completed()->visibleTo($request->user())->where('invoice_no', $term)->first();

            if (! $sale) {
                return redirect()->route('sale-returns.create')->withErrors(['invoice' => "Invoice \"{$term}\" was not found (or belongs to another branch)."])->withInput();
            }
        }

        return view('sale-returns.create', [
            'sale' => $sale?->load(['customer', 'branch', 'user']),
            'lines' => $sale ? $this->service->returnableLines($sale) : collect(),
            'methods' => Payment::METHODS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0'],
            'refund_method' => ['required', Rule::in(array_merge(['adjust'], array_keys(Payment::METHODS)))],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $sale = Sale::findOrFail($data['sale_id']);
        $this->ensureBranchAccess($sale->branch_id, $sale->user_id);

        $return = $this->service->create($request->user(), $sale, $data['items'], $data['refund_method'], $data['reason'] ?? null);

        return redirect()->route('sale-returns.show', $return)->with('success', "Return {$return->return_no} saved. ".money($return->total, true).' '.($return->refund_method === 'adjust' ? 'adjusted in khata.' : 'refunded.'));
    }

    public function show(SaleReturn $saleReturn): View
    {
        $this->ensureBranchAccess($saleReturn->branch_id, $saleReturn->user_id);

        return view('sale-returns.show', ['return' => $saleReturn->load(['items.product.unit', 'sale', 'customer', 'branch', 'user'])]);
    }

    public function receipt(SaleReturn $saleReturn, BarcodeService $barcodes): View
    {
        $this->ensureBranchAccess($saleReturn->branch_id, $saleReturn->user_id);

        return view('sale-returns.receipt', [
            'return' => $saleReturn->load(['items', 'sale', 'customer', 'branch', 'user']),
            'width' => settings('receipt_size', '80') === '58' ? 58 : 80,
            'barcode' => $barcodes->svg($saleReturn->return_no, 30),
        ]);
    }
}
