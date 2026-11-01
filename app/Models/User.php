<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    public const ROLES = ['owner', 'manager', 'cashier'];

    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'phone',
        'avatar',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** Never written to the activity log. */
    protected array $activityHidden = ['password', 'remember_token', 'last_login_at', 'last_login_ip'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    public function isCashier(): bool
    {
        return $this->hasRole('cashier');
    }

    public function getRoleNameAttribute(): ?string
    {
        return $this->getRoleNames()->first();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public function getInitialsAttribute(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
    }

    /** Reason this account may not sign in, or null when it may. */
    public function loginBlockReason(): ?string
    {
        if (! $this->is_active) {
            return 'Your account has been deactivated. Please contact the owner.';
        }

        if (! $this->isOwner()) {
            if (! $this->branch || $this->branch->trashed()) {
                return 'Your account is not assigned to a branch. Please contact the owner.';
            }
            if (! $this->branch->is_active) {
                return "The {$this->branch->name} branch is currently inactive. Please contact the owner.";
            }
        }

        return null;
    }
}
