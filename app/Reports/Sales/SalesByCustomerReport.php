<?php

namespace App\Reports\Sales;

use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;

class SalesByCustomerReport extends Report
{
    public string $key = 'sales-by-customer';
    public string $title = 'Sales by customer';
    public string $group = 'Sales';
    public string $description = 'Who buys the most, and how much of it is still unpaid.';
    public string $icon = 'users';

    public function run(ReportContext $ctx): ReportResult
    {
        $rows = $this->sales($ctx)->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->groupBy('customers.id', 'customers.name', 'customers.phone', 'customers.balance')
            ->selectRaw('customers.id, customers.name, customers.phone, customers.balance, COUNT(*) as invoices, SUM(sales.grand_total) as total,
                SUM(sales.paid_amount) as paid, SUM(sales.due_amount) as due, MAX(sales.sold_at) as last_visit')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'name' => $r->name, 'phone' => $r->phone, 'invoices' => (int) $r->invoices, 'total' => round((float) $r->total, 2),
                'paid' => round((float) $r->paid, 2), 'due' => round((float) $r->due, 2), 'balance' => round((float) $r->balance, 2),
                'last_visit' => $r->last_visit, '_url' => route('customers.show', $r->id),
            ]);

        $walkIn = (float) $this->sales($ctx)->whereNull('sales.customer_id')->sum('grand_total');

        return new ReportResult(
            cards: [
                ['Customers', (string) $rows->count(), 'users', 'primary'],
                ['Named customer sales', money($rows->sum('total')), 'receipt', 'success'],
                ['Walk-in sales', money($walkIn), 'user', 'info'],
                ['Still due on these invoices', money($rows->sum('due')), 'alert-triangle', 'danger'],
            ],
            columns: ['name' => ['Customer', 'text'], 'phone' => ['Phone', 'text'], 'invoices' => ['Invoices', 'number'], 'total' => ['Sales', 'money'],
                'paid' => ['Paid', 'money'], 'due' => ['Due', 'money'], 'balance' => ['Khata balance now', 'money'], 'last_visit' => ['Last visit', 'date']],
            rows: $rows,
            totals: ReportResult::sum($rows, ['invoices', 'total', 'paid', 'due']),
        );
    }
}
