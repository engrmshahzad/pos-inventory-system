<?php

namespace App\Traits;

use App\Support\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Writes created / updated / deleted events of a model to activity_logs.
 * A model can list attributes to skip in a protected $activityHidden array.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => $model->logActivity('created'));
        static::updated(fn ($model) => $model->logActivity('updated'));
        static::deleted(fn ($model) => $model->logActivity('deleted'));
    }

    protected function logActivity(string $action): void
    {
        $hidden = array_merge(['created_at', 'updated_at'], $this->activityHidden ?? []);
        $properties = [];

        if ($action === 'updated') {
            $changes = Arr::except($this->getChanges(), $hidden);
            if (empty($changes)) {
                return;
            }
            $properties = [
                'old' => Arr::only($this->getOriginal(), array_keys($changes)),
                'new' => $changes,
            ];
        }

        $label = $this->getAttribute('name') ?? '#'.$this->getKey();
        $type = Str::headline(class_basename($this));

        ActivityLogger::log($action, "{$type} \"{$label}\" {$action}", $this, $properties);
    }
}
