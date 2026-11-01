<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SaleReturn extends Model
{
    protected $fillable = ['branch_id', 'sale_id', 'customer_id', 'user_id', 'return_no', 'total', 'refund_method', 'reason', 'returned_at'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'returned_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query->when(currentBranchId(), fn ($q, $id) => $q->where('sale_returns.branch_id', $id));
        }

        $query->where('sale_returns.branch_id', $user->branch_id);

        return $user->isCashier() ? $query->where('sale_returns.user_id', $user->id) : $query;
    }

    public function getRefundLabelAttribute(): string
    {
        return $this->refund_method === 'adjust' ? 'Adjusted in khata' : (Payment::METHODS[$this->refund_method] ?? ucfirst($this->refund_method)).' refund';
    }
}
