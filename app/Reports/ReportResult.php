<?php

namespace App\Reports;

use Illuminate\Support\Collection;

class ReportResult
{
    /**
     * @param  array<int, array{0: string, 1: string, 2?: string, 3?: string}>  $cards  [label, formatted value, icon, colour]
     * @param  array<string, array{0: string, 1: string}>  $columns  key => [label, type]; types: text, money, number, qty, percent, date, datetime
     * @param  Collection<int, array>  $rows  each row keyed like $columns (+ optional _url, _style: total|subtotal|indent|heading)
     */
    public function __construct(
        public array $cards = [],
        public array $columns = [],
        public ?Collection $rows = null,
        public ?array $totals = null,
        public ?array $chart = null,
        public ?string $note = null,
        public bool $statement = false,
    ) {
        $this->rows ??= collect();
    }

    /** Sum the given columns over all rows. */
    public static function sum(Collection $rows, array $keys, string $label = 'Total'): array
    {
        $first = array_key_first($rows->first() ?? ['label' => null]);
        $totals = [$first => $label];
        foreach ($keys as $key) {
            $totals[$key] = round($rows->sum($key), 3);
        }

        return $totals;
    }
}
