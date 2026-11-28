<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * Show contact messages across the restaurants the user can access.
     */
    public function index(Request $request): Response
    {
        $profileIds = $request->user()->profiles()
            ->pluck('id')
            ->merge($request->user()->sharedRestaurants()->pluck('profiles.id'))
            ->unique()
            ->values();

        $messages = ContactMessage::query()
            ->with('profile:id,username,display_name,company')
            ->whereIn('profile_id', $profileIds)
            ->latest('id')
            ->limit(100)
            ->get();

        return Inertia::render('inbox/Index', [
            'messages' => $messages->map(fn (ContactMessage $message): array => [
                'id' => $message->id,
                'name' => $message->name,
                'email' => $message->email,
                'message' => $message->message,
                'read' => $message->isRead(),
                'page' => $message->profile->restaurantName(),
                'page_url' => route('bio.show', $message->profile->username),
                'created_human' => $message->created_at?->diffForHumans(),
                'created_at' => $message->created_at?->toDateTimeString(),
                'read_url' => route('inbox.update', $message),
                'delete_url' => route('inbox.destroy', $message),
            ])->all(),
            'unread' => ContactMessage::query()
                ->whereIn('profile_id', $profileIds)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    /**
     * Toggle whether a message has been read.
     */
    public function update(Request $request, ContactMessage $message): RedirectResponse
    {
        abort_unless($message->profile->canOperate($request->user()), 403);

        $message->update([
            'read_at' => $message->isRead() ? null : now(),
        ]);

        return back();
    }

    /**
     * Delete a message.
     */
    public function destroy(Request $request, ContactMessage $message): RedirectResponse
    {
        abort_unless($message->profile->canOperate($request->user()), 403);

        $message->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message deleted.')]);

        return back();
    }
}
