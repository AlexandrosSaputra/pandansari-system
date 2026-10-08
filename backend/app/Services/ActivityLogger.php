<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(User $actor, string $action, Model $subject, array $metadata = []): ActivityLog
    {
        return ActivityLog::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
