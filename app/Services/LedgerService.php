<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ledgers rebuilt from source documents.
 * Customer: debit = they owe more (sales, refunds paid out); credit = they owe less (payments, returns). Balance = receivable.
 * Supplier: credit = we owe more (purchases, refunds received); debit = we owe less (payments, returns). Balance = payable.
 */
class LedgerService
{
    /** @return array{opening: float, entries: Collection, closing: float, debit: float, credit: float} */
    public function customer(Customer $customer, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $entries = collect();

        if ((float) $customer->opening_balance != 0) {
            $entries->push($this->entry($customer->created_at, 'Opening balance', '—', (float) $customer->opening_balance, 0));
        }

        Sale::completed()->where('customer_id', $customer->id)->get(['id', 'invoice_no', 'grand_total', 'sold_at', 'branch_id'])
            ->each(fn ($s) => $entries->push($this->entry($s->sold_at, 'Sale', $s->invoice_no, (float) $s->grand_total, 0, route('sales.show', $s->id), $s->branch_id)));

        Payment::where('customer_id', $customer->id)->whereIn('type', ['sale', 'customer_receipt', 'refund'])->with('payable')->get()
            ->each(function (Payment $p) use ($entries) {
                $ref = $p->payment_no ?? ($p->payable instanceof Sale ? $p->payable->invoice_no : '—');
                $label = match ($p->type) {
                    'sale' => 'Paid at sale ('.$p->method_label.')',
                    'refund' => 'Refund paid ('.$p->method_label.')',
                    default => 'Payment received ('.$p->method_label.')',
                };
                $p->type === 'refund'
                    ? $entries->push($this->entry($p->paid_at, $label, $ref, (float) $p->amount, 0))
                    : $entries->push($this->entry($p->paid_at, $label, $ref, 0, (float) $p->amount));
            });

        SaleReturn::where('customer_id', $customer->id)->get()
            ->each(fn ($r) => $entries->push($this->entry($r->returned_at, 'Sale return', $r->return_no, 0, (float) $r->total, route('sale-returns.show', $r->id), $r->branch_id)));

        return $this->finish($entries, $from, $to, fn ($e) => $e['debit'] - $e['credit']);
    }

    public function supplier(Supplier $supplier, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $entries = collect();

        if ((float) $supplier->opening_balance != 0) {
            $entries->push($this->entry($supplier->created_at, 'Opening balance', '—', 0, (float) $supplier->opening_balance));
        }

        Purchase::received()->where('supplier_id', $supplier->id)->get(['id', 'purchase_no', 'grand_total', 'received_at', 'branch_id'])
            ->each(fn ($p) => $entries->push($this->entry($p->received_at, 'Purchase', $p->purchase_no, 0, (float) $p->grand_total, route('purchases.show', $p->id), $p->branch_id)));

        Payment::where('supplier_id', $supplier->id)->whereIn('type', ['purchase_payment', 'supplier_payment', 'supplier_refund'])->get()
            ->each(function (Payment $p) use ($entries) {
                $p->type === 'supplier_refund'
                    ? $entries->push($this->entry($p->paid_at, 'Refund received ('.$p->method_label.')', $p->payment_no ?? '—', 0, (float) $p->amount))
                    : $entries->push($this->entry($p->paid_at, 'Payment made ('.$p->method_label.')', $p->payment_no ?? '—', (float) $p->amount, 0));
            });

        PurchaseReturn::where('supplier_id', $supplier->id)->get()
            ->each(fn ($r) => $entries->push($this->entry($r->returned_at, 'Purchase return', $r->return_no, (float) $r->total, 0, route('purchase-returns.show', $r->id), $r->branch_id)));

        return $this->finish($entries, $from, $to, fn ($e) => $e['credit'] - $e['debit']);
    }

    protected function entry($date, string $description, string $reference, float $debit, float $credit, ?string $url = null, ?int $branchId = null): array
    {
        return ['date' => Carbon::parse($date), 'description' => $description, 'reference' => $reference, 'debit' => round($debit, 2), 'credit' => round($credit, 2), 'url' => $url, 'branch_id' => $branchId];
    }

    protected function finish(Collection $entries, ?Carbon $from, ?Carbon $to, callable $effect): array
    {
        $entries = $entries->sortBy(fn ($e) => $e['date']->format('YmdHis'))->values();

        $opening = $from ? round($entries->filter(fn ($e) => $e['date']->lt($from->copy()->startOfDay()))->sum($effect), 2) : 0.0;
        $inRange = $entries->filter(fn ($e) => (! $from || $e['date']->gte($from->copy()->startOfDay())) && (! $to || $e['date']->lte($to->copy()->endOfDay())));

        $running = $opening;
        $rows = $inRange->map(function ($e) use (&$running, $effect) {
            $running = round($running + $effect($e), 2);

            return $e + ['balance' => $running];
        })->values();

        return [
            'opening' => $opening,
            'entries' => $rows,
            'closing' => $running,
            'debit' => round($rows->sum('debit'), 2),
            'credit' => round($rows->sum('credit'), 2),
        ];
    }
}
