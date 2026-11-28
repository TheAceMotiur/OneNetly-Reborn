<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show every bio page in the application.
     */
    public function index(): Response
    {
        return Inertia::render('admin/profiles/Index', [
            'profiles' => Profile::query()
                ->select(['id', 'user_id', 'username', 'custom_domain', 'display_name', 'template', 'is_published', 'created_at'])
                ->with('user:id,name,email')
                ->withCount('links')
                ->withSum('links', 'clicks')
                ->latest()
                ->paginate(15),
            'stats' => [
                'total' => Profile::count(),
                'published' => Profile::where('is_published', true)->count(),
                'customDomains' => Profile::whereNotNull('custom_domain')->count(),
            ],
        ]);
    }

    /**
     * Publish, hide or assign a custom domain to the given bio page.
     */
    public function update(Request $request, Profile $profile): RedirectResponse
    {
        $data = $request->validate([
            'is_published' => ['sometimes', 'boolean'],
            'custom_domain' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^(?!-)[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/',
                Rule::unique('profiles', 'custom_domain')->ignore($profile->id),
            ],
        ]);

        if (array_key_exists('custom_domain', $data)) {
            $data['custom_domain'] = filled($data['custom_domain'])
                ? strtolower(trim((string) $data['custom_domain']))
                : null;

            if ($data['custom_domain'] !== $profile->custom_domain) {
                $data['custom_domain_token'] = $data['custom_domain'] !== null
                    ? Str::random(32)
                    : null;
                $data['custom_domain_verified_at'] = null;
            }
        }

        $profile->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page updated.')]);

        return back();
    }

    /**
     * Remove the given bio page and its links.
     */
    public function destroy(Profile $profile): RedirectResponse
    {
        if ($profile->orders()->exists() || $profile->reservations()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This page has orders or bookings and cannot be deleted. Unpublish it instead so the records are retained.'),
            ]);

            return back();
        }

        $username = $profile->username;

        $profile->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('The page for :username has been deleted.', ['username' => $username]),
        ]);

        return back();
    }
}
