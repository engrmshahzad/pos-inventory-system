<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    public const STATUSES = [
        'in_progress' => ['In progress', 'info'],
        'applied' => ['Applied', 'success'],
        'cancelled' => ['Cancelled', 'slate'],
    ];

    protected $fillable = ['count_no', 'branch_id', 'category_id', 'status', 'note', 'created_by', 'stock_adjustment_id', 'applied_at'];

    protected function casts(): array
    {
        return ['applied_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query->when(currentBranchId(), fn ($q, $id) => $q->where('stock_counts.branch_id', $id));
        }

        return $query->where('stock_counts.branch_id', $user->branch_id);
    }

    public function getStatusBadgeAttribute(): array
    {
        return self::STATUSES[$this->status] ?? [ucfirst($this->status), 'slate'];
    }
}
