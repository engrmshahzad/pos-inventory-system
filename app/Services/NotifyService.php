<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class NotifyService
{
    /** Active managers of a branch (and owners when $withOwners). The acting user is never notified. */
    public function branchManagers(int $branchId, bool $withOwners = false): Collection
    {
        $managers = User::role('manager')->active()->where('branch_id', $branchId)->get();

        return ($withOwners ? $managers->merge($this->owners()) : $managers)
            ->reject(fn (User $u) => $u->id === Auth::id())
            ->unique('id');
    }

    public function owners(): Collection
    {
        return User::role('owner')->active()->get()->reject(fn (User $u) => $u->id === Auth::id());
    }

    public function send(Collection $users, AppNotification $notification): void
    {
        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }
    }
}
