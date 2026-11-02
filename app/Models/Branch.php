<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Branch extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'code', 'phone', 'email', 'address', 'city', 'is_active'];

    /** Tables (added in later phases) whose rows make a branch undeletable. */
    public const TRANSACTION_TABLES = [
        'sales', 'purchases', 'sale_returns', 'purchase_returns',
        'stock_adjustments', 'expenses', 'payments', 'stock_movements', 'stock_counts',
    ];

    /** Dot colours used in the branch switcher and badges. */
    public const COLORS = ['#4F46E5', '#10B981', '#F59E0B', '#0EA5E9', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getColorAttribute(): string
    {
        return self::COLORS[($this->id - 1) % count(self::COLORS)];
    }

    public function hasTransactions(): bool
    {
        if (! $this->id) {
            return false;
        }

        foreach (self::TRANSACTION_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('branch_id', $this->id)->exists()) {
                return true;
            }
        }

        // Transfers reference a branch on either side.
        return Schema::hasTable('stock_transfers')
            && DB::table('stock_transfers')->where('from_branch_id', $this->id)->orWhere('to_branch_id', $this->id)->exists();
    }
}
