<?php

namespace App\Reports\Money;

use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use App\Support\Sql;
use Illuminate\Support\Facades\DB;

/**
 * Shared aging logic: unpaid invoice amounts bucketed by invoice age. On "All branches" an extra column holds what is
 * not tied to an invoice (opening balance, advances, khata adjustments) so the row total equals the current balance.
 */
abstract class AgingReport extends Report
{
    public bool $usesDates = false;

    public const BUCKETS = ['b0' => '0–30 days', 'b30' => '31–60 days', 'b60' => '61–90 days', 'b90' => '90+ days'];

    abstract protected function invoices(ReportContext $ctx); // query with: party_id, due, age

    abstract protected function parties(); // id => [name, phone, balance]

    abstract protected function partyUrl(int $id): string;

    public function run(ReportContext $ctx): ReportResult
    {
        $byParty = $this->invoices($ctx)->get()->groupBy('party_id');
        $parties = $this->parties();
        $showOther = $ctx->branchId === null;

        $ids = $showOther ? $parties->filter(fn ($p) => (float) $p->balance != 0)->keys()->merge($byParty->keys())->unique() : $byParty->keys();

        $rows = $ids->map(function ($id) use ($byParty, $parties, $showOther) {
            $party = $parties[$id] ?? null;
            $buckets = ['b0' => 0, 'b30' => 0, 'b60' => 0, 'b90' => 0];
            foreach ($byParty[$id] ?? [] as $inv) {
                $age = (int) $inv->age;
                $key = $age <= 30 ? 'b0' : ($age <= 60 ? 'b30' : ($age <= 90 ? 'b60' : 'b90'));
                $buckets[$key] += (float) $inv->due;
            }
            $invoiceTotal = array_sum($buckets);
            $row = ['name' => $party?->name ?? '—', 'phone' => $party?->phone] + array_map(fn ($v) => round($v, 2), $buckets);
            if ($showOther) {
                $row['other'] = round((float) ($party?->balance ?? 0) - $invoiceTotal, 2);
                $row['total'] = round((float) ($party?->balance ?? 0), 2);
            } else {
                $row['total'] = round($invoiceTotal, 2);
            }

            return $row + ['_url' => $this->partyUrl((int) $id)];
        })->filter(fn ($r) => $r['total'] != 0)->sortByDesc('total')->values();

        $columns = ['name' => [$this->partyLabel(), 'text'], 'phone' => ['Phone', 'text']];
        foreach (self::BUCKETS as $k => $label) {
            $columns[$k] = [$label, 'money'];
        }
        if ($showOther) {
            $columns['other'] = ['Opening / advance', 'money'];
        }
        $columns['total'] = ['Total', 'money'];

        $sums = collect(array_keys(self::BUCKETS))->mapWithKeys(fn ($k) => [$k => round($rows->sum($k), 2)]);

        return new ReportResult(
            cards: [
                ['Total outstanding', money($rows->where('total', '>', 0)->sum('total')), 'wallet', 'danger'],
                [$this->partyLabel().'s', (string) $rows->where('total', '>', 0)->count(), 'users', 'primary'],
                ['Older than 90 days', money($sums['b90']), 'alert-triangle', 'warning'],
            ],
            columns: $columns,
            rows: $rows,
            totals: ReportResult::sum($rows, array_values(array_diff(array_keys($columns), ['name', 'phone']))),
            chart: $this->moneyTooltipChart('bar', array_values(self::BUCKETS), [['name' => 'Outstanding', 'data' => $sums->values()->all()]],
                ['plotOptions' => ['bar' => ['borderRadius' => 6, 'columnWidth' => '45%', 'distributed' => true]], 'colors' => ['#10B981', '#F59E0B', '#F97316', '#EF4444'], 'legend' => ['show' => false]]),
            note: $showOther ? '"Opening / advance" is the part of the balance not linked to an invoice (opening balance, advances, khata adjustments).' : 'Branch view: unpaid invoices of this branch only.',
        );
    }

    abstract protected function partyLabel(): string;
}
