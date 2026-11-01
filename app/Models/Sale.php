<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sale extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_HOLD = 'hold';

    public const PAYMENT_BADGES = [
        'paid' => ['success', 'Paid'],
        'partial' => ['warning', 'Partial'],
        'due' => ['danger', 'Due'],
    ];

    protected $fillable = [
        'branch_id', 'invoice_no', 'status', 'customer_id', 'user_id', 'cash_register_id',
        'subtotal', 'discount_type', 'discount_value', 'discount_amount', 'tax_amount', 'shipping',
        'grand_total', 'paid_amount', 'due_amount', 'change_amount', 'payment_status', 'note', 'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeHeld(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_HOLD);
    }

    /** Sales visible to the user: owner by branch switcher, others their branch; cashiers only their own. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query->when(currentBranchId(), fn ($q, $id) => $q->where('sales.branch_id', $id));
        }

        $query->where('sales.branch_id', $user->branch_id);

        return $user->isCashier() ? $query->where('sales.user_id', $user->id) : $query;
    }

    public function getPaymentBadgeAttribute(): array
    {
        return self::PAYMENT_BADGES[$this->payment_status] ?? ['slate', ucfirst($this->payment_status)];
    }

    public function getProfitAttribute(): float
    {
        $cost = $this->items->sum(fn (SaleItem $i) => (float) $i->purchase_price * (float) $i->quantity);

        return round((float) $this->grand_total - (float) $this->tax_amount - (float) $this->shipping - $cost, 2);
    }
}
