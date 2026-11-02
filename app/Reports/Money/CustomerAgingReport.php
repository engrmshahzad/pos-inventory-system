<?php

namespace App\Reports\Money;

use App\Models\Customer;
use App\Reports\ReportContext;
use App\Support\Sql;
use Illuminate\Support\Facades\DB;

class CustomerAgingReport extends AgingReport
{
    public string $key = 'customer-aging';
    public string $title = 'Customer due (aging)';
    public string $group = 'Money';
    public string $description = 'Who owes you, split by how old the unpaid invoices are: 0–30, 31–60, 61–90, 90+ days.';
    public string $icon = 'users';

    protected function invoices(ReportContext $ctx)
    {
        return DB::table('sales')->where('status', 'completed')->where('due_amount', '>', 0)->whereNotNull('customer_id')
            ->when($ctx->branchId, fn ($q) => $q->where('branch_id', $ctx->branchId))
            ->selectRaw('customer_id as party_id, due_amount as due, '.Sql::ageDays('sold_at').' as age');
    }

    protected function parties()
    {
        return Customer::withTrashed()->get(['id', 'name', 'phone', 'balance'])->keyBy('id');
    }

    protected function partyUrl(int $id): string
    {
        return route('customers.show', $id);
    }

    protected function partyLabel(): string
    {
        return 'Customer';
    }
}
