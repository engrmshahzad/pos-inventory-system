<?php

namespace App\Exports;

use App\Reports\ReportResult;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Any report as a sheet: title rows, header, data (raw numbers), totals. */
class ReportExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    protected int $headerRow = 4;

    public function __construct(protected string $title, protected string $subtitle, protected ReportResult $result)
    {
    }

    public function array(): array
    {
        $columns = $this->result->columns;
        $out = [[$this->title], [$this->subtitle], []];
        $out[] = array_map(fn ($c) => $c[0], array_values($columns));

        foreach ($this->result->rows as $row) {
            $out[] = $this->line($row, $columns);
        }
        if ($this->result->totals) {
            $out[] = $this->line($this->result->totals, $columns);
        }

        return $out;
    }

    protected function line(array $row, array $columns): array
    {
        return collect($columns)->map(function ($col, $key) use ($row) {
            $value = $row[$key] ?? null;
            $type = $row['_type'] ?? $col[1];

            return in_array($type, ['money', 'number', 'qty', 'percent'], true) && is_numeric($value) ? (float) $value : (is_scalar($value) ? (string) $value : '');
        })->values()->all();
    }

    public function styles(Worksheet $sheet): array
    {
        $last = $this->headerRow + $this->result->rows->count() + 1;

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true, 'color' => ['rgb' => '64748B']]],
            $this->headerRow => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEF2FF']]],
            $last => $this->result->totals ? ['font' => ['bold' => true]] : [],
        ];
    }

    public function title(): string
    {
        return substr(preg_replace('/[^A-Za-z0-9 ]/', '', $this->title), 0, 31);
    }
}
