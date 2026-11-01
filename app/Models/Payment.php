<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** type => [label, badge colour, direction (in = money received, out = money paid)] */
    public const TYPES = [
        'sale' => ['Sale payment', 'success', 'in'],
        'customer_receipt' => ['Customer receipt', 'primary', 'in'],
        'supplier_refund' => ['Supplier refund', 'info', 'in'],
        'purchase_payment' => ['Paid at purchase', 'warning', 'out'],
        'supplier_payment' => ['Supplier payment', 'violet', 'out'],
        'refund' => ['Sale refund', 'danger', 'out'],
    ];

    public const TYPES_IN = ['sale', 'customer_receipt', 'supplier_refund'];
    public const TYPES_OUT = ['purchase_payment', 'supplier_payment', 'refund'];

    protected $fillable = [
        'payment_no', 'branch_id', 'type', 'payable_type', 'payable_id', 'customer_id', 'supplier_id', 'method', 'amount',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query->when(currentBranchId(), fn ($q, $id) => $q->where('payments.branch_id', $id));
        }

        return $query->where('payments.branch_id', $user->branch_id);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type][0] ?? ucfirst($this->type);
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPES[$this->type][1] ?? 'slate';
    }

    public function getIsIncomingAttribute(): bool
    {
        return in_array($this->type, self::TYPES_IN, true);
    }

    /** Who the money came from / went to. */
    public function getPartyNameAttribute(): string
    {
        return $this->customer?->name ?? $this->supplier?->name ?? 'Walk-in Customer';
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->method] ?? ucfirst($this->method);
    }
}
