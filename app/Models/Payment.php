<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    public const METHODS = [
        'cash' => 'Cash',
        'card' => 'Card',
        'jazzcash' => 'JazzCash',
        'easypaisa' => 'Easypaisa',
        'bank' => 'Bank Transfer',
    ];

    public const TYPES = [
        'sale' => 'Sale payment',
        'customer_receipt' => 'Received from customer',
    ];

    protected $fillable = [
        'branch_id', 'type', 'payable_type', 'payable_id', 'customer_id', 'method', 'amount',
        'reference', 'note', 'cash_register_id', 'user_id', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->method] ?? ucfirst($this->method);
    }
}
