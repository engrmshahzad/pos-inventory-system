<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id', 'product_id', 'product_name', 'quantity', 'unit_cost', 'discount', 'tax_percent',
        'tax_amount', 'line_total', 'new_sale_price', 'expiry_date', 'batch_no',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'new_sale_price' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    /** Cost per unit actually paid: line total (after discount, incl. tax) ÷ qty. */
    public function getNetUnitCostAttribute(): float
    {
        return (float) $this->quantity > 0 ? round((float) $this->line_total / (float) $this->quantity, 2) : 0.0;
    }
}
