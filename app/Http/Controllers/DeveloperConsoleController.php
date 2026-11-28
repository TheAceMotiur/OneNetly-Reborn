<?php

namespace App\Http\Controllers;

use App\Models\PosToken;
use App\Models\Profile;
use App\Models\WebhookDelivery;
use App\Services\PosWebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DeveloperConsoleController extends Controller
{
    /**
     * Show the developer console for the user's restaurants.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        /** @var Collection<int, Profile> $restaurants */
        $restaurants = $user !== null ? $user->accessibleRestaurants() : collect();
        $selected = $this->selectedRestaurant($request, $restaurants);

        return Inertia::render('developers/Index', [
            'restaurants' => $restaurants->map(fn (Profile $profile): array => [
                'username' => $profile->username,
                'name' => $profile->restaurantName(),
                'public_url' => route('restaurant.show', $profile->username),
                'is_published' => $profile->is_published,
                'accepting_orders' => $profile->accepting_orders,
                'token_count' => $profile->posTokens()->count(),
                'has_webhook' => filled($profile->pos_webhook_url),
            ])->all(),
            'selected' => $selected instanceof Profile ? [
                'username' => $selected->username,
                'name' => $selected->restaurantName(),
                'public_url' => route('restaurant.show', $selected->username),
                'menu_url' => route('restaurant.menu', $selected),
                'is_published' => $selected->is_published,
                'ordering_enabled' => $selected->menu_ordering_enabled,
                'accepting_orders' => $selected->accepting_orders,
                'token_count' => $selected->posTokens()->count(),
                'has_webhook' => filled($selected->pos_webhook_url),
            ] : null,
            'pos' => $selected instanceof Profile ? $this->posPayload($selected) : null,
        ]);
    }

    /**
     * Save the webhook and ordering settings for one restaurant.
     */
    public function updatePos(Request $request, Profile $profile): RedirectResponse
    {
        abort_unless($profile->canManage($request->user()), 403);

        $data = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:64'],
            'accepting_orders' => ['required', 'boolean'],
        ]);

        $url = filled($data['webhook_url'] ?? null) ? $data['webhook_url'] : null;
        $secret = filled($data['webhook_secret'] ?? null) ? $data['webhook_secret'] : $profile->pos_webhook_secret;
        $generatedSecret = false;

        if ($url !== null && blank($secret)) {
            $secret = Str::random(40);
            $generatedSecret = true;
        }

        $profile->update([
            'pos_webhook_url' => $url,
            'pos_webhook_secret' => $secret,
            'accepting_orders' => $request->boolean('accepting_orders'),
        ]);

        if ($generatedSecret) {
            Inertia::flash('pos_secret', $secret);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Developer settings saved.'),
        ]);

        return back();
    }

    /**
     * Send a test event so the team can verify the webhook.
     */
    public function testPosWebhook(Request $request, Profile $profile, PosWebhookDispatcher $dispatcher): RedirectResponse
    {
        abort_unless($profile->canManage($request->user()), 403);

        $delivery = $dispatcher->test($profile);

        Inertia::flash('toast', $delivery->success
            ? ['type' => 'success', 'message' => __('Webhook delivered successfully.')]
            : ['type' => 'error', 'message' => __('Webhook failed: :error', ['error' => $delivery->error ?? 'Unknown error'])]);

        return back();
    }

    /**
     * Issue a new API token for one restaurant.
     */
    public function storePosToken(Request $request, Profile $profile): RedirectResponse
    {
        abort_unless($profile->canManage($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        [$token, $plain] = PosToken::issue($profile, $data['name']);

        Inertia::flash('pos_token', $plain);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Token :name created.', ['name' => $token->name]),
        ]);

        return back();
    }

    /**
     * Revoke an API token.
     */
    public function destroyPosToken(Request $request, Profile $profile, PosToken $token): RedirectResponse
    {
        abort_unless($profile->canManage($request->user()), 403);
        abort_unless($token->profile_id === $profile->id, 404);

        $token->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('API token revoked.'),
        ]);

        return back();
    }

    /**
     * Build the API and webhook payload for the selected restaurant.
     *
     * @return array<string, mixed>
     */
    private function posPayload(Profile $profile): array
    {
        return [
            'webhook_url' => $profile->pos_webhook_url,
            'has_secret' => filled($profile->pos_webhook_secret),
            'accepting_orders' => $profile->accepting_orders,
            'api_base' => url('/api/pos'),
            'tokens' => $profile->posTokens()->get()->map(fn (PosToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'prefix' => $token->token_prefix,
                'last_used_human' => $token->last_used_at?->diffForHumans(),
                'created_human' => $token->created_at?->diffForHumans(),
                'destroy_url' => route('developer.pos.tokens.destroy', [$profile, $token]),
            ])->all(),
            'deliveries' => $profile->webhookDeliveries()->limit(6)->get()->map(fn (WebhookDelivery $delivery): array => [
                'id' => $delivery->id,
                'event' => $delivery->event,
                'success' => $delivery->success,
                'status' => $delivery->response_status,
                'error' => $delivery->error,
                'attempts' => $delivery->attempts,
                'created_human' => $delivery->created_at?->diffForHumans(),
            ])->all(),
            'urls' => [
                'update' => route('developer.pos.update', $profile),
                'test' => route('developer.pos.test', $profile),
                'tokens' => route('developer.pos.tokens.store', $profile),
            ],
        ];
    }

    /**
     * Resolve the restaurant shown in the console.
     *
     * @param  Collection<int, Profile>  $restaurants
     */
    private function selectedRestaurant(Request $request, Collection $restaurants): ?Profile
    {
        $username = trim((string) $request->query('restaurant', ''));

        if ($username !== '') {
            $match = $restaurants->firstWhere('username', $username);

            if ($match instanceof Profile) {
                return $match;
            }
        }

        return $restaurants->first();
    }
}
