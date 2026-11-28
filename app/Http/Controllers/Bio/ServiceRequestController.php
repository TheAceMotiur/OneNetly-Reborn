<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ServiceRequestController extends Controller
{
    /**
     * Send a waiter, bill or cleaning request from a table.
     */
    public function store(Request $request, string $username): RedirectResponse
    {
        $profile = Profile::query()
            ->where('username', $username)
            ->where('is_published', true)
            ->firstOrFail();

        if (filled($request->input('website'))) {
            return back();
        }

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(ServiceRequest::TYPES))],
            'table_number' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $profile->serviceRequests()->create([
            'type' => $data['type'],
            'table_number' => $data['table_number'] ?? null,
            'status' => ServiceRequest::STATUS_PENDING,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('A team member is on the way.'),
        ]);

        return back();
    }
}
