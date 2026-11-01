<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\BarcodeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarcodeController extends Controller
{
    public const SIZES = [
        '38x25' => ['label' => '38 × 25 mm (thermal roll)', 'width' => 38, 'height' => 25],
        '50x25' => ['label' => '50 × 25 mm (thermal roll)', 'width' => 50, 'height' => 25],
        'a4' => ['label' => 'A4 sheet (65 labels, 38 × 21 mm)', 'width' => 38.1, 'height' => 21.2],
    ];

    public function index(Request $request): View
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));

        $selected = Product::whereIn('id', $ids)->get(['id', 'name', 'sku', 'barcode', 'sale_price'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'barcode' => $p->barcode, 'price_label' => money($p->sale_price), 'qty' => 1])
            ->values();

        return view('products.barcodes', ['selected' => $selected, 'sizes' => self::SIZES]);
    }

    public function print(Request $request, BarcodeService $barcodes): View
    {
        $data = $request->validate([
            'size' => ['required', 'in:'.implode(',', array_keys(self::SIZES))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:500'],
            'show_price' => ['nullable', 'boolean'],
            'show_name' => ['nullable', 'boolean'],
        ]);

        $qty = collect($data['items'])->mapWithKeys(fn ($i) => [(int) $i['id'] => (int) $i['qty']]);
        abort_if($qty->sum() > 2000, 422, 'Please print at most 2,000 labels at a time.');

        $products = Product::whereIn('id', $qty->keys())->get()->keyBy('id');

        $labels = $qty->flatMap(function ($count, $id) use ($products, $barcodes) {
            $product = $products->get($id);
            if (! $product) {
                return [];
            }
            $svg = $barcodes->svg($product->barcode);

            return array_fill(0, $count, ['product' => $product, 'svg' => $svg]);
        });

        return view('products.barcodes-print', [
            'labels' => $labels,
            'size' => self::SIZES[$data['size']] + ['key' => $data['size']],
            'showPrice' => $request->boolean('show_price', true),
            'showName' => $request->boolean('show_name', true),
        ]);
    }
}
