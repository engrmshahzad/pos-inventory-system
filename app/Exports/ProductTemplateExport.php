<?php

namespace App\Exports;

use App\Models\Branch;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Import template: product columns plus one opening-stock column per branch (stock_hyd, stock_khi...). */
class ProductTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public const COLUMNS = [
        'name', 'sku', 'barcode', 'category', 'brand', 'unit',
        'purchase_price', 'sale_price', 'wholesale_price', 'tax_percent', 'min_stock', 'track_expiry', 'description',
    ];

    public function headings(): array
    {
        return array_merge(self::COLUMNS, self::stockColumns());
    }

    public function array(): array
    {
        $stock = array_fill(0, count(self::stockColumns()), 10);

        return [
            array_merge(['Tapal Danedar 190g', '', '', 'Beverages', 'Tapal', 'pcs', 330, 380, 360, 0, 10, 'no', 'Example row: delete before importing'], $stock),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('B:C')->getNumberFormat()->setFormatCode('@');

        return [1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEF2FF']]]];
    }

    /** @return list<string> */
    public static function stockColumns(): array
    {
        return Branch::orderBy('name')->pluck('code')->map(fn ($code) => 'stock_'.strtolower($code))->all();
    }
}
