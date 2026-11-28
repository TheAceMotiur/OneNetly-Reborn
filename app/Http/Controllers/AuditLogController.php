<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Show the actions recorded for one restaurant.
     */
    public function __invoke(Request $request, Profile $profile): Response
    {
        abort_unless($profile->canManage($request->user()), 403);

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->where('profile_id', $profile->id)
            ->latest('id')
            ->limit(200)
            ->get();

        return Inertia::render('activity/Index', [
            'logs' => $logs->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => str($log->action)->after('.')->replace('_', ' ')->headline()->toString(),
                'description' => $log->description,
                'user' => $log->user instanceof User ? $log->user->name : __('System'),
                'created_at' => $log->created_at?->toDayDateTimeString(),
                'created_human' => $log->created_at?->diffForHumans(),
            ])->all(),
            'restaurant' => [
                'username' => $profile->username,
                'name' => $profile->restaurantName(),
            ],
        ]);
    }
}
