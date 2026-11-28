<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Record an action performed on a page by its owner or team.
     *
     * @param  array<string, mixed>  $meta
     */
    public function log(Profile $profile, string $action, string $description, ?Model $subject = null, array $meta = []): AuditLog
    {
        return AuditLog::query()->create([
            'profile_id' => $profile->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
