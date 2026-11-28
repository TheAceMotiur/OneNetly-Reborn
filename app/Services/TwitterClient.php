<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwitterClient
{
    /**
     * Determine whether the account can talk to X right now.
     */
    public function isConfigured(?User $account): bool
    {
        return $account instanceof User
            && (bool) setting('twitter_enabled', true)
            && $account->twitter_enabled
            && filled($account->twitter_user_id)
            && filled($account->twitter_access_token);
    }

    /**
     * Check the saved credentials with the authenticated user endpoint.
     *
     * @return array{ok: bool, message: string}
     */
    public function verify(User $account): array
    {
        if (! $this->isConfigured($account)) {
            return [
                'ok' => false,
                'message' => __('Enable Twitter and save your user ID and access token first.'),
            ];
        }

        try {
            $token = $this->accessToken($account);
            $response = Http::withToken((string) $token)
                ->acceptJson()
                ->timeout(15)
                ->get($this->baseUrl().'/users/me', [
                    'user.fields' => 'id,name,username',
                ]);

            if ($response->successful()) {
                $username = (string) ($response->json('data.username') ?? $account->twitter_username ?? 'account');
                $account->update(['twitter_username' => $username]);

                return [
                    'ok' => true,
                    'message' => __('Connected to @:account.', ['account' => $username]),
                ];
            }

            return [
                'ok' => false,
                'message' => $this->errorMessage($response),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'message' => __('Could not reach X. Check the connection and try again.'),
            ];
        }
    }

    /**
     * Create a tweet, optionally as a reply.
     *
     * @return array{ok: bool, id: string|null, error: string|null, data: array<string, mixed>|null}
     */
    public function createTweet(Profile $profile, string $body, ?string $replyToTweetId = null): array
    {
        $account = $profile->user;

        if (! $account instanceof User || ! $this->isConfigured($account) || trim($body) === '') {
            return [
                'ok' => false,
                'id' => null,
                'error' => __('Twitter is not connected for this account.'),
                'data' => null,
            ];
        }

        $payload = ['text' => $body];

        if ($replyToTweetId !== null && $replyToTweetId !== '') {
            $payload['reply'] = ['in_reply_to_tweet_id' => $replyToTweetId];
        }

        try {
            $response = Http::withToken((string) $this->accessToken($account))
                ->acceptJson()
                ->timeout(15)
                ->post($this->baseUrl().'/tweets', $payload);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'id' => (string) $response->json('data.id'),
                    'error' => null,
                    'data' => $response->json(),
                ];
            }

            return [
                'ok' => false,
                'id' => null,
                'error' => $this->errorMessage($response),
                'data' => $response->json(),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'id' => null,
                'error' => $exception->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Fetch recent posts that mention the connected account.
     *
     * @return array{ok: bool, mentions: array<int, array{tweet_id: string, author_id: string|null, author_username: string|null, body: string, created_at: string|null, meta: array<string, mixed>}>, error: string|null}
     */
    public function mentions(Profile $profile, ?string $sinceId = null): array
    {
        $account = $profile->user;

        if (! $account instanceof User || ! $this->isConfigured($account)) {
            return [
                'ok' => false,
                'mentions' => [],
                'error' => __('Twitter is not connected for this account.'),
            ];
        }

        $params = [
            'max_results' => 20,
            'tweet.fields' => 'author_id,created_at,conversation_id',
            'expansions' => 'author_id',
            'user.fields' => 'name,username',
        ];

        if ($sinceId !== null && $sinceId !== '') {
            $params['since_id'] = $sinceId;
        }

        try {
            $response = Http::withToken((string) $this->accessToken($account))
                ->acceptJson()
                ->timeout(15)
                ->get(
                    $this->baseUrl().'/users/'.rawurlencode((string) $account->twitter_user_id).'/mentions',
                    $params,
                );

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'mentions' => [],
                    'error' => $this->errorMessage($response),
                ];
            }

            /** @var array<string, string> $usernames */
            $usernames = [];

            foreach ((array) $response->json('includes.users', []) as $user) {
                if (is_array($user) && isset($user['id'])) {
                    $usernames[(string) $user['id']] = (string) ($user['username'] ?? '');
                }
            }

            $mentions = [];

            foreach ((array) $response->json('data', []) as $tweet) {
                if (! is_array($tweet) || ! isset($tweet['id'])) {
                    continue;
                }

                $authorId = isset($tweet['author_id']) ? (string) $tweet['author_id'] : null;

                $mentions[] = [
                    'tweet_id' => (string) $tweet['id'],
                    'author_id' => $authorId,
                    'author_username' => $authorId !== null ? ($usernames[$authorId] ?? null) : null,
                    'body' => (string) ($tweet['text'] ?? ''),
                    'created_at' => isset($tweet['created_at']) ? (string) $tweet['created_at'] : null,
                    'meta' => $tweet,
                ];
            }

            return [
                'ok' => true,
                'mentions' => $mentions,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'mentions' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Return a usable access token, refreshing it when it has expired.
     */
    private function accessToken(User $account): ?string
    {
        $expiresAt = $account->twitter_token_expires_at;

        if ($expiresAt !== null && now()->addMinute()->greaterThanOrEqualTo($expiresAt)) {
            $refreshed = $this->refreshToken($account);

            if ($refreshed !== null) {
                return $refreshed;
            }
        }

        return $account->twitter_access_token;
    }

    /**
     * Refresh the OAuth 2.0 access token and persist the new credentials.
     */
    private function refreshToken(User $account): ?string
    {
        $refreshToken = (string) $account->twitter_refresh_token;
        $clientId = (string) setting('twitter_client_id');
        $clientSecret = (string) setting('twitter_client_secret');

        if ($refreshToken === '' || $clientId === '') {
            return null;
        }

        try {
            $request = Http::asForm()->acceptJson()->timeout(15);

            if ($clientSecret !== '') {
                $request = $request->withBasicAuth($clientId, $clientSecret);
            }

            $payload = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ];

            if ($clientSecret === '') {
                $payload['client_id'] = $clientId;
            }

            $response = $request->post('https://api.x.com/2/oauth2/token', $payload);

            if (! $response->successful() || blank($response->json('access_token'))) {
                return null;
            }

            $expiresIn = (int) $response->json('expires_in', 7200);

            $account->update([
                'twitter_access_token' => (string) $response->json('access_token'),
                'twitter_refresh_token' => (string) ($response->json('refresh_token') ?? $refreshToken),
                'twitter_token_expires_at' => now()->addSeconds($expiresIn),
            ]);

            return (string) $response->json('access_token');
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Extract a useful error message from an X API response.
     */
    private function errorMessage(Response $response): string
    {
        $detail = $response->json('detail')
            ?? $response->json('title')
            ?? $response->json('errors.0.message')
            ?? __('X rejected the request.');

        return (string) $detail;
    }

    /**
     * The X API base URL.
     */
    private function baseUrl(): string
    {
        return 'https://api.x.com/2';
    }
}
