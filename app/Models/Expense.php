<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'expense_no', 'branch_id', 'expense_category_id', 'amount', 'expense_date', 'method', 'paid_to', 'note',
        'attachment', 'cash_register_id', 'user_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expense_date' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query->when(currentBranchId(), fn ($q, $id) => $q->where('expenses.branch_id', $id));
        }

        return $query->where('expenses.branch_id', $user->branch_id);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment ? Storage::disk('public')->url($this->attachment) : null;
    }

    public function getMethodLabelAttribute(): string
    {
        return Payment::METHODS[$this->method] ?? ucfirst($this->method);
    }

    /** Expense name for activity log (no "name" column). */
    public function getNameAttribute(): string
    {
        return $this->expense_no ?? '';
    }
}
