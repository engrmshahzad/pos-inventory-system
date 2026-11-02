<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    public const STATUSES = [
        'draft' => ['Draft', 'slate'],
        'sent' => ['In transit', 'info'],
        'received' => ['Received', 'success'],
        'partially_received' => ['Partially received', 'warning'],
        'cancelled' => ['Cancelled', 'danger'],
    ];

    protected $fillable = [
        'transfer_no', 'from_branch_id', 'to_branch_id', 'status', 'is_request', 'transfer_date', 'note',
        'created_by', 'sent_by', 'sent_at', 'received_by', 'received_at', 'receive_note',
        'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_request' => 'boolean',
            'transfer_date' => 'date',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id')->withTrashed();
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by')->withTrashed();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    /** Owner sees all (or the selected branch's in+out); others only transfers touching their branch. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $branchId = $user->isOwner() ? currentBranchId() : $user->branch_id;

        return $query->when($branchId, fn ($q) => $q->where(fn ($q) => $q->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId)));
    }

    public function getStatusBadgeAttribute(): array
    {
        return self::STATUSES[$this->status] ?? [ucfirst($this->status), 'slate'];
    }

    public function getHasShortageAttribute(): bool
    {
        return $this->status === 'partially_received';
    }
}
