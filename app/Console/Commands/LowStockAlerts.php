<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Notifications\AppNotification;
use App\Services\NotifyService;
use Illuminate\Console\Command;

/** Daily: tell each branch's managers (and the owner) how many products are low or out of stock. */
class LowStockAlerts extends Command
{
    protected $signature = 'pos:low-stock-alerts';

    protected $description = 'Send low-stock notifications to branch managers and the owner';

    public function handle(NotifyService $notify): int
    {
        if (! (int) settings('low_stock_alerts', '1')) {
            $this->info('Low-stock alerts are switched off in Settings.');

            return self::SUCCESS;
        }

        foreach (Branch::active()->get() as $branch) {
            $rows = BranchStock::where('branch_id', $branch->id)
                ->join('products', 'products.id', '=', 'branch_stock.product_id')
                ->whereNull('products.deleted_at')->where('products.is_active', true)->where('products.min_stock', '>', 0)
                ->whereColumn('branch_stock.quantity', '<=', 'products.min_stock')
                ->selectRaw('SUM(CASE WHEN branch_stock.quantity <= 0 THEN 1 ELSE 0 END) as out_count, COUNT(*) as low_count')->first();

            if ((int) $rows->low_count === 0) {
                continue;
            }

            $notify->send($notify->branchManagers($branch->id, withOwners: true), new AppNotification(
                "Low stock at {$branch->code}",
                "{$rows->low_count} products are at or below their low-stock level ({$rows->out_count} out of stock) at {$branch->name}.",
                route('reports.show', ['key' => 'low-stock', 'branch' => $branch->id]), 'alert-triangle', 'warning', 'low_stock',
            ));
            $this->line("{$branch->code}: {$rows->low_count} low");
        }

        return self::SUCCESS;
    }
}
