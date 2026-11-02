<?php

namespace App\Reports\Money;

use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class ExpenseReport extends Report
{
    public string $key = 'expenses';
    public string $title = 'Expense report';
    public string $group = 'Money';
    public string $description = 'All expenses in the period with a breakdown by category.';
    public string $icon = 'wallet';

    public function filters(): array
    {
        return ['category' => ['Category', 'select', ['' => 'All categories'] + ExpenseCategory::orderBy('name')->pluck('name', 'id')->all(), '']];
    }

    public function run(ReportContext $ctx): ReportResult
    {
        $base = DB::table('expenses')->join('expense_categories as c', 'c.id', '=', 'expenses.expense_category_id')
            ->join('branches as b', 'b.id', '=', 'expenses.branch_id')
            ->whereNull('expenses.deleted_at')
            ->whereBetween('expenses.expense_date', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('expenses.branch_id', $ctx->branchId))
            ->when($ctx->param('category'), fn ($q, $c) => $q->where('expenses.expense_category_id', $c));

        $rows = (clone $base)->orderByDesc('expenses.expense_date')
            ->get(['expenses.expense_no', 'expenses.expense_date', 'b.code', 'c.name as category', 'expenses.paid_to', 'expenses.method', 'expenses.note', 'expenses.amount'])
            ->map(fn ($r) => [
                'number' => $r->expense_no, 'date' => $r->expense_date, 'branch' => $r->code, 'category' => $r->category, 'paid_to' => $r->paid_to,
                'method' => Payment::METHODS[$r->method] ?? $r->method, 'note' => $r->note, 'amount' => (float) $r->amount,
            ]);

        $byCategory = $rows->groupBy('category')->map(fn ($g) => round($g->sum('amount'), 2))->sortDesc();
        $days = max(1, $ctx->from->diffInDays($ctx->to) + 1);

        return new ReportResult(
            cards: [['Total expenses', money($rows->sum('amount')), 'wallet', 'danger'], ['Entries', (string) $rows->count(), 'file-text', 'primary'],
                ['Average per day', money($rows->sum('amount') / $days), 'calendar', 'info'], ['Biggest category', $byCategory->keys()->first() ?? '—', 'folder', 'warning']],
            columns: ['number' => ['No', 'text'], 'date' => ['Date', 'date'], 'branch' => ['Branch', 'text'], 'category' => ['Category', 'text'], 'paid_to' => ['Paid to', 'text'], 'method' => ['Method', 'text'], 'note' => ['Note', 'text'], 'amount' => ['Amount', 'money']],
            rows: $rows,
            totals: ReportResult::sum($rows, ['amount']),
            chart: $byCategory->isNotEmpty() ? $this->donut($byCategory->keys()->all(), $byCategory->values()->all()) : null,
        );
    }
}
