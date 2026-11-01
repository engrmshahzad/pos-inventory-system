<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'company', 'phone', 'email', 'address', 'city', 'ntn', 'opening_balance', 'balance', 'is_active'];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $term ? $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('company', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")) : $query;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->company ? "{$this->name} ({$this->company})" : $this->name;
    }
}
