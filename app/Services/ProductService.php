<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(protected StockService $stock)
    {
    }

    /**
     * @param  array<int|string, float|string|null>  $openingStock  [branch_id => quantity]
     */
    public function create(array $data, ?UploadedFile $image = null, array $openingStock = []): Product
    {
        return DB::transaction(function () use ($data, $image, $openingStock) {
            $data['sku'] = filled($data['sku'] ?? null) ? $data['sku'] : $this->nextSku();
            $data['barcode'] = filled($data['barcode'] ?? null) ? $data['barcode'] : $this->generateEan13();

            $product = Product::create(Arr::except($data, ['image', 'remove_image', 'opening_stock']));

            if ($image) {
                $product->update(['image' => $image->store('products', 'public')]);
            }

            $this->stock->ensureRowsForProduct($product);

            $validBranches = Branch::pluck('id')->all();
            foreach ($openingStock as $branchId => $qty) {
                if ((float) $qty > 0 && in_array((int) $branchId, $validBranches, true)) {
                    $this->stock->increase((int) $branchId, $product->id, (float) $qty, 'opening', $product, $product->sku, 'Opening stock', (float) $product->purchase_price);
                }
            }

            return $product;
        });
    }

    public function update(Product $product, array $data, ?UploadedFile $image = null, bool $removeImage = false): Product
    {
        return DB::transaction(function () use ($product, $data, $image, $removeImage) {
            $data['sku'] = filled($data['sku'] ?? null) ? $data['sku'] : $product->sku;
            $data['barcode'] = filled($data['barcode'] ?? null) ? $data['barcode'] : $product->barcode;

            $product->update(Arr::except($data, ['image', 'remove_image', 'opening_stock']));

            if ($image || $removeImage) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $product->update(['image' => $image?->store('products', 'public')]);
            }

            return $product;
        });
    }

    public function delete(Product $product): void
    {
        // Soft delete keeps stock history and old invoices intact.
        $product->delete();
    }

    public function nextSku(): string
    {
        $next = (int) Product::withTrashed()->max('id') + 1;

        do {
            $sku = 'PRD-'.str_pad((string) $next++, 6, '0', STR_PAD_LEFT);
        } while (Product::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }

    /** EAN-13 in the 200–299 "in-store" prefix range, unique across products. */
    public function generateEan13(): string
    {
        do {
            $base = '200'.str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
            $code = $base.self::ean13CheckDigit($base);
        } while (Product::withTrashed()->where('barcode', $code)->exists());

        return $code;
    }

    public static function ean13CheckDigit(string $twelveDigits): int
    {
        $sum = 0;
        foreach (str_split($twelveDigits) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }

    public static function isValidEan13(string $code): bool
    {
        return (bool) preg_match('/^\d{13}$/', $code) && self::ean13CheckDigit(substr($code, 0, 12)) === (int) $code[12];
    }
}
