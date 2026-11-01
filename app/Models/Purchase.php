<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Purchase extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_ORDERED = 'ordered';

    protected $fillable = [
        'branch_id', 'supplier_id', 'user_id', 'purchase_no', 'reference_no', 'purchase_date', 'status',
        'subtotal', 'discount', 'tax_amount', 'shipping', 'grand_total', 'paid_amount', 'due_amount',
        'payment_status', 'note', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'received_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RECEIVED);
    }

    /** Owner: branch switcher; others: own branch. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query->when(currentBranchId(), fn ($q, $id) => $q->where('purchases.branch_id', $id));
        }

        return $query->where('purchases.branch_id', $user->branch_id);
    }

    public function getPaymentBadgeAttribute(): array
    {
        return Sale::PAYMENT_BADGES[$this->payment_status] ?? ['slate', ucfirst($this->payment_status)];
    }

    public function getStatusBadgeAttribute(): array
    {
        return $this->status === self::STATUS_RECEIVED ? ['success', 'Received'] : ['info', 'Ordered'];
    }
}
