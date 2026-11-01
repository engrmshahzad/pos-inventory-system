<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Services\ProductService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Imports products row by row. Valid rows are saved; invalid rows are collected in $failures
 * so the user gets a report instead of an all-or-nothing error.
 */
class ProductsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var list<array{row: int, name: string, errors: list<string>}> */
    public array $failures = [];

    /** @var array<string, int> branch code (lowercase) => id */
    protected array $branchIds;

    public function __construct(protected ProductService $service, protected ?int $onlyBranchId = null)
    {
        $this->branchIds = Branch::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtolower($code) => $id])->all();
    }

    public function collection(Collection $rows): void
    {
        $seenSku = [];
        $seenBarcode = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // heading is row 1
            $data = $row->map(fn ($v) => is_string($v) ? trim($v) : $v)->all();

            if (collect($data)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }

            $data['sku'] = $this->code($data['sku'] ?? null, upper: true);
            $data['barcode'] = $this->code($data['barcode'] ?? null);

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'sku' => ['nullable', 'max:50', 'regex:/^[A-Z0-9\-_.]+$/', Rule::unique('products', 'sku')],
                'barcode' => ['nullable', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('products', 'barcode')],
                'unit' => ['required', Rule::exists('units', 'short_name')],
                'purchase_price' => ['required', 'numeric', 'min:0'],
                'sale_price' => ['required', 'numeric', 'min:0'],
                'wholesale_price' => ['nullable', 'numeric', 'min:0'],
                'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'min_stock' => ['nullable', 'numeric', 'min:0'],
                'category' => ['nullable', 'string', 'max:100'],
                'brand' => ['nullable', 'string', 'max:100'],
            ], [
                'unit.exists' => 'Unit ":input" not found. Use a short name such as pcs, kg, ltr.',
            ]);

            $errors = $validator->errors()->all();

            if ($data['sku'] && in_array($data['sku'], $seenSku, true)) {
                $errors[] = 'SKU is repeated in the file.';
            }
            if ($data['barcode'] && in_array($data['barcode'], $seenBarcode, true)) {
                $errors[] = 'Barcode is repeated in the file.';
            }

            $opening = $this->openingStock($data, $errors);

            if ($errors) {
                $this->failures[] = ['row' => $rowNumber, 'name' => (string) ($data['name'] ?? '—'), 'errors' => $errors];

                continue;
            }

            $this->service->create([
                'name' => $data['name'],
                'sku' => $data['sku'],
                'barcode' => $data['barcode'],
                'category_id' => $this->categoryId($data['category'] ?? null),
                'brand_id' => $this->brandId($data['brand'] ?? null),
                'unit_id' => Unit::where('short_name', strtolower($data['unit']))->value('id'),
                'purchase_price' => $data['purchase_price'],
                'sale_price' => $data['sale_price'],
                'wholesale_price' => filled($data['wholesale_price'] ?? null) ? $data['wholesale_price'] : null,
                'tax_percent' => filled($data['tax_percent'] ?? null) ? $data['tax_percent'] : 0,
                'min_stock' => filled($data['min_stock'] ?? null) ? $data['min_stock'] : 0,
                'track_expiry' => in_array(strtolower((string) ($data['track_expiry'] ?? '')), ['yes', '1', 'true', 'y'], true),
                'description' => $data['description'] ?? null,
                'is_active' => true,
            ], null, $opening);

            $seenSku[] = $data['sku'];
            $seenBarcode[] = $data['barcode'];
            $this->imported++;
        }
    }

    /** Excel turns long numeric codes into floats (8.96E+12); turn them back into digit strings. */
    protected function code(mixed $value, bool $upper = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = is_int($value) || is_float($value) ? number_format($value, 0, '', '') : (string) $value;

        return $upper ? strtoupper($value) : $value;
    }

    /** @return array<int, float> */
    protected function openingStock(array $data, array &$errors): array
    {
        $opening = [];

        foreach ($data as $key => $value) {
            if (! Str::startsWith((string) $key, 'stock_') || $value === null || $value === '') {
                continue;
            }

            $code = Str::after($key, 'stock_');
            $branchId = $this->branchIds[$code] ?? null;

            if (! $branchId) {
                $errors[] = "Unknown branch column \"{$key}\".";
            } elseif (! is_numeric($value) || $value < 0) {
                $errors[] = "{$key} must be a number of 0 or more.";
            } elseif ($this->onlyBranchId === null || $this->onlyBranchId === $branchId) {
                $opening[$branchId] = (float) $value;
            }
        }

        return $opening;
    }

    protected function categoryId(?string $name): ?int
    {
        if (! $name) {
            return null;
        }

        return Category::whereNull('parent_id')->where('name', $name)->value('id')
            ?? Category::where('name', $name)->value('id')
            ?? Category::create(['name' => $name, 'color' => '#4F46E5', 'is_active' => true])->id;
    }

    protected function brandId(?string $name): ?int
    {
        if (! $name) {
            return null;
        }

        return Brand::where('name', $name)->value('id') ?? Brand::create(['name' => $name])->id;
    }
}
