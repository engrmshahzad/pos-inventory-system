<?php

namespace App\Reports\Money;

use App\Models\Supplier;
use App\Reports\ReportContext;
use App\Support\Sql;
use Illuminate\Support\Facades\DB;

class SupplierAgingReport extends AgingReport
{
    public string $key = 'supplier-payable';
    public string $title = 'Supplier payable (aging)';
    public string $group = 'Money';
    public string $description = 'What you owe each supplier, split by the age of unpaid purchases.';
    public string $icon = 'factory';

    protected function invoices(ReportContext $ctx)
    {
        return DB::table('purchases')->where('status', 'received')->where('due_amount', '>', 0)
            ->when($ctx->branchId, fn ($q) => $q->where('branch_id', $ctx->branchId))
            ->selectRaw('supplier_id as party_id, due_amount as due, '.Sql::ageDays('purchase_date').' as age');
    }

    protected function parties()
    {
        return Supplier::withTrashed()->get(['id', 'name', 'phone', 'balance'])->keyBy('id');
    }

    protected function partyUrl(int $id): string
    {
        return route('suppliers.show', $id);
    }

    protected function partyLabel(): string
    {
        return 'Supplier';
    }
}
