<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected Builder $query, protected ?int $branchId)
    {
    }

    public function query(): Builder
    {
        return $this->query->with(['category.parent', 'brand', 'unit']);
    }

    public function headings(): array
    {
        return [
            'Name', 'SKU', 'Barcode', 'Category', 'Brand', 'Unit',
            'Purchase Price', 'Sale Price', 'Wholesale Price', 'Tax %', 'Min Stock',
            $this->branchId ? 'Stock (branch)' : 'Stock (all branches)', 'Stock Value (cost)', 'Active',
        ];
    }

    /** @param Product $product */
    public function map($product): array
    {
        return [
            $product->name,
            $product->sku,
            $product->barcode,
            $product->category?->full_name,
            $product->brand?->name,
            $product->unit?->short_name,
            (float) $product->purchase_price,
            (float) $product->sale_price,
            $product->wholesale_price !== null ? (float) $product->wholesale_price : null,
            (float) $product->tax_percent,
            (float) $product->min_stock,
            (float) $product->stock_qty,
            round((float) $product->stock_qty * (float) $product->purchase_price, 2),
            $product->is_active ? 'Yes' : 'No',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('C')->getNumberFormat()->setFormatCode('@');

        return [1 => ['font' => ['bold' => true]]];
    }
}
