<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    public const REASONS = [
        'damaged' => 'Damaged',
        'expired' => 'Expired',
        'lost' => 'Lost / theft',
        'found' => 'Found',
        'correction' => 'Correction',
        'internal_use' => 'Internal use',
    ];

    public const STATUSES = [
        'pending' => ['Pending approval', 'warning'],
        'approved' => ['Approved', 'success'],
        'rejected' => ['Rejected', 'danger'],
    ];

    protected $fillable = [
        'adjustment_no', 'branch_id', 'adjustment_date', 'reason', 'source', 'status', 'total_value', 'note',
        'created_by', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return ['adjustment_date' => 'date', 'approved_at' => 'datetime', 'total_value' => 'decimal:2'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query->when(currentBranchId(), fn ($q, $id) => $q->where('stock_adjustments.branch_id', $id));
        }

        return $query->where('stock_adjustments.branch_id', $user->branch_id);
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst(str_replace('_', ' ', $this->reason));
    }

    public function getStatusBadgeAttribute(): array
    {
        return self::STATUSES[$this->status] ?? [ucfirst($this->status), 'slate'];
    }
}
