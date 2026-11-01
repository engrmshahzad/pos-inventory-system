<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    protected $fillable = ['purchase_return_id', 'purchase_item_id', 'product_id', 'product_name', 'quantity', 'unit_cost', 'total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
