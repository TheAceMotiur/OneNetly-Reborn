<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Services\FacebookNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FacebookWebhookController extends Controller
{
    /**
     * Answer Meta's webhook verification challenge.
     */
    public function verify(Request $request): Response
    {
        $token = trim((string) setting('facebook_verify_token'));

        abort_if($token === '', 403);

        abort_unless(
            $request->query('hub_mode') === 'subscribe'
            && hash_equals($token, (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Store Messenger messages and comment events.
     */
    public function handle(Request $request): JsonResponse
    {
        $secret = trim((string) setting('facebook_app_secret'));

        if ($secret === '' || ! $this->signatureIsValid($request, $secret)) {
            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($request->input('object') !== 'page') {
            return response()->json(['message' => 'ok']);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            $profile = $this->profileFor((string) ($entry['id'] ?? ''));

            if (! $profile instanceof Profile || ! $profile->user?->hasToolAccess('facebook')) {
                continue;
            }

            $notifier = app(FacebookNotifier::class);

            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                $notifier->handleMessenger($profile, (array) $event);
            }

            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? '') !== 'feed') {
                    continue;
                }

                $notifier->handleComment($profile, (array) ($change['value'] ?? []));
            }
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * Find the workspace connected to a Facebook Page ID.
     */
    private function profileFor(string $pageId): ?Profile
    {
        if ($pageId === '') {
            return null;
        }

        return User::query()
            ->where('facebook_page_id', $pageId)
            ->first()
            ?->workspaceProfile();
    }

    /**
     * Verify Meta's HMAC signature over the raw request body.
     */
    private function signatureIsValid(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Hub-Signature-256');

        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
