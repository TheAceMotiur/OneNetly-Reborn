<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\Profile;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'totalUsers' => User::count(),
                'verifiedUsers' => User::whereNotNull('email_verified_at')->count(),
                'adminUsers' => User::where('is_admin', true)->count(),
                'newUsersThisWeek' => User::where('created_at', '>=', now()->subWeek())->count(),
                'totalProfiles' => Profile::count(),
                'publishedProfiles' => Profile::where('is_published', true)->count(),
                'totalLinks' => Link::count(),
                'clicksThisWeek' => LinkClick::where('clicked_at', '>=', now()->subDays(6)->startOfDay())->count(),
            ],
            'recentUsers' => User::query()
                ->select(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at'])
                ->latest()
                ->take(5)
                ->get(),
            'topProfiles' => Profile::query()
                ->select(['id', 'user_id', 'username', 'display_name', 'is_published'])
                ->with('user:id,name')
                ->withCount('links')
                ->withSum('links', 'clicks')
                ->orderByDesc('links_sum_clicks')
                ->take(5)
                ->get(),
        ]);
    }
}
