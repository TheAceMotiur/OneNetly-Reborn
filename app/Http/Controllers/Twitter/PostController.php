<?php

namespace App\Http\Controllers\Twitter;

use App\Http\Controllers\Controller;
use App\Models\TwitterPost;
use App\Services\TwitterPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostController extends Controller
{
    /**
     * Save a tweet as a draft.
     */
    public function store(Request $request, TwitterPublisher $publisher): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:280'],
            'publish_now' => ['sometimes', 'boolean'],
        ]);

        $post = $profile->twitterPosts()->create([
            'body' => trim($data['body']),
            'status' => 'draft',
        ]);

        if ($request->boolean('publish_now')) {
            $published = $publisher->publish($post);

            Inertia::flash('toast', [
                'type' => $published ? 'success' : 'error',
                'message' => $published
                    ? __('Tweet published.')
                    : (string) ($post->refresh()->error ?? __('Draft saved, but it could not be published.')),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Draft saved.'),
        ]);

        return back();
    }

    /**
     * Publish a tweet immediately.
     */
    public function publish(Request $request, TwitterPost $post, TwitterPublisher $publisher): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($post->profile_id === $profile->id, 404);
        abort_if($post->status === 'published', 422);

        $published = $publisher->publish($post);

        Inertia::flash('toast', [
            'type' => $published ? 'success' : 'error',
            'message' => $published
                ? __('Tweet published.')
                : (string) (TwitterPost::query()->whereKey($post->id)->value('error') ?? __('Could not publish the tweet.')),
        ]);

        return back();
    }

    /**
     * Schedule a tweet for later.
     */
    public function schedule(Request $request, TwitterPost $post): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($post->profile_id === $profile->id, 404);
        abort_if($post->status === 'published', 422);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $post->update([
            'status' => 'scheduled',
            'scheduled_at' => $data['scheduled_at'],
            'error' => null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tweet scheduled.'),
        ]);

        return back();
    }

    /**
     * Delete a stored tweet.
     */
    public function destroy(Request $request, TwitterPost $post): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($post->profile_id === $profile->id, 404);

        $post->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Post removed.'),
        ]);

        return back();
    }
}
