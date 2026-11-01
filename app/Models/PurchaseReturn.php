<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseReturn extends Model
{
    protected $fillable = ['branch_id', 'purchase_id', 'supplier_id', 'user_id', 'return_no', 'total', 'settle_method', 'reason', 'returned_at'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'returned_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query->when(currentBranchId(), fn ($q, $id) => $q->where('purchase_returns.branch_id', $id));
        }

        return $query->where('purchase_returns.branch_id', $user->branch_id);
    }

    public function getSettleLabelAttribute(): string
    {
        return $this->settle_method === 'adjust' ? 'Deducted from payable' : (Payment::METHODS[$this->settle_method] ?? ucfirst($this->settle_method)).' refund received';
    }
}
