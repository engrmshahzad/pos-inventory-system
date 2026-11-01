<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActivityLogger
{
    public static function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?int $branchId = null): ActivityLog
    {
        $request = app()->runningInConsole() ? null : request();

        return ActivityLog::create([
            'user_id' => Auth::id(),
            'branch_id' => $branchId ?? static::resolveBranchId($subject),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => Str::limit($description, 250),
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250) : null,
        ]);
    }

    protected static function resolveBranchId(?Model $subject): ?int
    {
        if ($subject instanceof \App\Models\Branch) {
            return $subject->getKey();
        }

        return $subject?->getAttribute('branch_id') ?? (Auth::check() ? currentBranchId() : null);
    }
}
