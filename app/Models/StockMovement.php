<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'opening' => ['Opening', 'slate'],
        'purchase' => ['Purchase', 'success'],
        'sale' => ['Sale', 'primary'],
        'sale_return' => ['Sale return', 'info'],
        'sale_cancel' => ['Sale cancelled', 'danger'],
        'purchase_return' => ['Purchase return', 'warning'],
        'transfer_in' => ['Transfer in', 'success'],
        'transfer_out' => ['Transfer out', 'warning'],
        'adjustment' => ['Adjustment', 'violet'],
    ];

    protected $fillable = [
        'branch_id', 'product_id', 'type', 'quantity', 'balance_after', 'unit_cost',
        'reference_type', 'reference_id', 'reference_no', 'user_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type][0] ?? ucfirst($this->type);
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPES[$this->type][1] ?? 'slate';
    }
}
