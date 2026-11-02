<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Batches with an expiry date (from received purchases) at branches that still hold stock of the product.
 * Sales don't pick a batch, so "suggested write-off" is min(batch qty, branch stock) — the user can edit it.
 */
class ExpiryService
{
    public function warningDays(): int
    {
        return (int) settings('expiry_warning_days', 30);
    }

    public function batches(?int $branchId, ?int $days = null): Collection
    {
        $days ??= $this->warningDays();
        $limit = today()->addDays($days)->toDateString();

        return DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->join('branches', 'branches.id', '=', 'purchases.branch_id')
            ->join('branch_stock', fn ($j) => $j->on('branch_stock.product_id', '=', 'purchase_items.product_id')->on('branch_stock.branch_id', '=', 'purchases.branch_id'))
            ->where('purchases.status', 'received')
            ->whereNotNull('purchase_items.expiry_date')
            ->where('purchase_items.expiry_date', '<=', $limit)
            ->where('branch_stock.quantity', '>', 0)
            ->whereNull('products.deleted_at')
            ->when($branchId, fn ($q) => $q->where('purchases.branch_id', $branchId))
            ->orderBy('purchase_items.expiry_date')
            ->get([
                'purchase_items.id', 'purchase_items.product_id', 'purchase_items.batch_no', 'purchase_items.expiry_date',
                'purchase_items.quantity as batch_qty', 'purchases.purchase_no', 'purchases.id as purchase_id', 'purchases.branch_id',
                'branches.name as branch_name', 'branches.code as branch_code', 'products.name', 'products.sku', 'products.purchase_price',
                'branch_stock.quantity as stock',
            ])
            ->map(function ($row) {
                $expiry = Carbon::parse($row->expiry_date);
                $row->days_left = (int) today()->diffInDays($expiry, false);
                $row->expired = $row->days_left < 0;
                $row->suggested = min((float) $row->batch_qty, (float) $row->stock);
                $row->value = round($row->suggested * (float) $row->purchase_price, 2);

                return $row;
            });
    }
}
