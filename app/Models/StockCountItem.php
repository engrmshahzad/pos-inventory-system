<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    protected $fillable = ['stock_count_id', 'product_id', 'system_quantity', 'counted_quantity'];

    protected function casts(): array
    {
        return ['system_quantity' => 'decimal:3', 'counted_quantity' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getDifferenceAttribute(): ?float
    {
        return $this->counted_quantity === null ? null : round((float) $this->counted_quantity - (float) $this->system_quantity, 3);
    }
}
