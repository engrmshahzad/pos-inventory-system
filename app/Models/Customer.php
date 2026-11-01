<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'phone', 'address', 'city', 'cnic', 'credit_limit', 'opening_balance', 'balance', 'is_active'];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
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
        if (! $term) {
            return $query;
        }

        $digits = preg_replace('/\D/', '', $term);

        return $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->when(strlen($digits) >= 3, fn ($q) => $q->orWhereRaw("REPLACE(REPLACE(phone, '-', ''), ' ', '') LIKE ?", ["%{$digits}%"])));
    }

    /** Phone in international format for wa.me links: 0300-1234567 -> 923001234567. */
    public function getWhatsappNumberAttribute(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->phone);

        return str_starts_with($digits, '0') ? '92'.substr($digits, 1) : $digits;
    }
}
