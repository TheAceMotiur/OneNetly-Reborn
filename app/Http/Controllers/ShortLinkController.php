<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShortLink\StoreShortLinkRequest;
use App\Http\Requests\ShortLink\UpdateShortLinkRequest;
use App\Models\ShortLink;
use App\Models\ShortLinkClick;
use App\Models\User;
use App\Services\ShortLinkTracker;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShortLinkController extends Controller
{
    public function __construct(
        private readonly ShortLinkTracker $tracker,
    ) {}

    /**
     * Show the short links of the signed-in user with their weekly traffic.
     */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $search = mb_strtolower(trim($request->string('q')->toString()));
        $status = $request->string('status')->toString();

        $all = $user->shortLinks()->get();

        $links = $all
            ->filter(function (ShortLink $link) use ($search, $status): bool {
                if ($status !== '' && $link->status() !== $status) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower((string) $link->title), $search)
                    || str_contains(mb_strtolower($link->slug), $search)
                    || str_contains(mb_strtolower($link->destination), $search);
            })
            ->values();

        $weekStart = now()->subDays(6)->startOfDay();

        $weekTotals = ShortLinkClick::query()
            ->whereIn('short_link_id', $links->pluck('id'))
            ->where('clicked_at', '>=', $weekStart)
            ->selectRaw('short_link_id, COUNT(*) as total')
            ->groupBy('short_link_id')
            ->pluck('total', 'short_link_id');

        $weekVisitors = ShortLinkClick::query()
            ->whereIn('short_link_id', $links->pluck('id'))
            ->where('clicked_at', '>=', $weekStart)
            ->selectRaw('short_link_id, COUNT(DISTINCT visitor_hash) as visitors')
            ->groupBy('short_link_id')
            ->pluck('visitors', 'short_link_id');

        return Inertia::render('short-links/Index', [
            'links' => $links->map(fn (ShortLink $link): array => $this->linkPayload(
                $link,
                (int) ($weekTotals[$link->id] ?? 0),
                (int) ($weekVisitors[$link->id] ?? 0),
            ))->all(),
            'stats' => [
                'links' => $all->count(),
                'live' => $all->filter(fn (ShortLink $link): bool => $link->isLive())->count(),
                'clicks' => (int) $all->sum('clicks'),
                'clicks_week' => (int) $weekTotals->sum(),
                'visitors_week' => (int) $weekVisitors->sum(),
            ],
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $status,
            ],
            'statuses' => ShortLink::STATUSES,
        ]);
    }

    /**
     * Create a short link, generating a slug when the user leaves it empty.
     */
    public function store(StoreShortLinkRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $shortLink = $this->user($request)->shortLinks()->create([
            'title' => $data['title'] ?? null,
            'slug' => $this->slug($data['slug'] ?? null),
            'destination' => (string) $data['destination'],
            'mobile_destination' => $data['mobile_destination'] ?? null,
            'password' => filled($data['password'] ?? null) ? Hash::make((string) $data['password']) : null,
            'expires_at' => $data['expires_at'] ?? null,
            'max_clicks' => $data['max_clicks'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Short link created: :url', ['url' => $shortLink->shortUrl()]),
        ]);

        return to_route('short-links.index');
    }

    /**
     * Update the settings of an existing short link.
     */
    public function update(UpdateShortLinkRequest $request, ShortLink $shortLink): RedirectResponse
    {
        $data = $request->validated();

        $attributes = [
            'title' => $data['title'] ?? null,
            'destination' => (string) $data['destination'],
            'mobile_destination' => $data['mobile_destination'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'max_clicks' => $data['max_clicks'] ?? null,
            'is_active' => $request->boolean('is_active', $shortLink->is_active),
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
        ];

        if (filled($data['slug'] ?? null)) {
            $attributes['slug'] = strtolower((string) $data['slug']);
        }

        if ($request->boolean('remove_password')) {
            $attributes['password'] = null;
        } elseif (filled($request->input('password'))) {
            $attributes['password'] = Hash::make((string) $request->input('password'));
        }

        $shortLink->update($attributes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Short link updated.'),
        ]);

        return back();
    }

    /**
     * Remove a short link and its click history.
     */
    public function destroy(Request $request, ShortLink $shortLink): RedirectResponse
    {
        $this->ensureOwner($request, $shortLink);

        $shortLink->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Short link deleted.'),
        ]);

        return to_route('short-links.index');
    }

    /**
     * Show the click analytics of one short link.
     */
    public function analytics(Request $request, ShortLink $shortLink): Response
    {
        $this->ensureOwner($request, $shortLink);

        $start = now()->subDays(29)->startOfDay();

        $daily = $shortLink->clicks()
            ->where('clicked_at', '>=', $start)
            ->selectRaw('DATE(clicked_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        /** @var list<array{date: string, clicks: int}> $series */
        $series = [];

        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            $date = now()->subDays($daysAgo)->toDateString();
            $series[] = ['date' => $date, 'clicks' => (int) ($daily[$date] ?? 0)];
        }

        $referrers = $shortLink->clicks()
            ->where('clicked_at', '>=', $start)
            ->whereNotNull('referrer')
            ->selectRaw('referrer, COUNT(*) as total')
            ->groupBy('referrer')
            ->orderByDesc('total')
            ->limit(50)
            ->get()
            ->groupBy(fn (ShortLinkClick $row): string => $this->referrerHost((string) $row->getAttribute('referrer')))
            ->map(fn (Collection $rows, string $source): array => [
                'source' => $source,
                'clicks' => (int) $rows->sum(fn (ShortLinkClick $row): int => (int) $row->getAttribute('total')),
            ])
            ->sortByDesc('clicks')
            ->take(5)
            ->values()
            ->all();

        $devices = $shortLink->clicks()
            ->where('clicked_at', '>=', $start)
            ->selectRaw("COALESCE(device, 'unknown') as label, COUNT(*) as total")
            ->groupByRaw("COALESCE(device, 'unknown')")
            ->orderByDesc('total')
            ->get()
            ->map(fn (ShortLinkClick $row): array => [
                'device' => (string) $row->getAttribute('label'),
                'clicks' => (int) $row->getAttribute('total'),
            ])
            ->all();

        $countries = $shortLink->clicks()
            ->where('clicked_at', '>=', $start)
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as total')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn (ShortLinkClick $row): array => [
                'country' => (string) $row->getAttribute('country'),
                'clicks' => (int) $row->getAttribute('total'),
            ])
            ->all();

        $recent = $shortLink->clicks()
            ->latest('clicked_at')
            ->limit(25)
            ->get()
            ->map(fn (ShortLinkClick $click): array => [
                'id' => $click->id,
                'clicked_at' => $click->clicked_at->toIso8601String(),
                'referrer' => $click->referrer,
                'device' => $click->device,
                'country' => $click->country,
            ])
            ->all();

        return Inertia::render('short-links/Analytics', [
            'link' => $this->linkPayload($shortLink),
            'totals' => [
                'clicks' => (int) $shortLink->clicks,
                'visitors' => (int) $shortLink->clicks()->distinct()->count('visitor_hash'),
                'today' => $shortLink->clicks()->where('clicked_at', '>=', now()->startOfDay())->count(),
                'week' => $shortLink->clicks()->where('clicked_at', '>=', now()->subDays(6)->startOfDay())->count(),
            ],
            'series' => $series,
            'referrers' => $referrers,
            'devices' => $devices,
            'countries' => $countries,
            'recent' => $recent,
        ]);
    }

    /**
     * Send a visitor to the destination of a short link and record the click.
     */
    public function redirect(Request $request, string $slug): Response|RedirectResponse
    {
        $shortLink = ShortLink::query()->where('slug', $slug)->first();

        abort_if($shortLink === null, 404);

        $status = $shortLink->status();

        abort_if($status === ShortLink::STATUS_PAUSED, 404);
        abort_if(in_array($status, [ShortLink::STATUS_EXPIRED, ShortLink::STATUS_EXHAUSTED], true), 410);

        if ($shortLink->hasPassword() && ! $this->unlocked($request, $shortLink)) {
            return Inertia::render('short-links/Password', [
                'slug' => $shortLink->slug,
                'title' => $shortLink->title,
            ]);
        }

        $this->tracker->record($request, $shortLink);

        return redirect()->away($shortLink->destinationFor($request));
    }

    /**
     * Unlock a password protected link for the current session.
     */
    public function unlock(Request $request, string $slug): RedirectResponse
    {
        $shortLink = ShortLink::query()->where('slug', $slug)->firstOrFail();

        abort_unless($shortLink->status() === ShortLink::STATUS_LIVE, 404);
        abort_unless($shortLink->hasPassword(), 404);

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check((string) $request->input('password'), (string) $shortLink->password)) {
            throw ValidationException::withMessages([
                'password' => __('The password you entered is incorrect.'),
            ]);
        }

        $request->session()->put($this->sessionKey($shortLink), true);

        return redirect()->to(route('short-links.redirect', $shortLink->slug, false));
    }

    /**
     * Render a QR code for one of the user's short links.
     */
    public function qr(Request $request, ShortLink $shortLink): HttpResponse
    {
        $this->ensureOwner($request, $shortLink);

        $renderer = new ImageRenderer(
            new RendererStyle(320, 1),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($shortLink->shortUrl());

        $response = response($svg, 200, ['Content-Type' => 'image/svg+xml']);

        if ($request->boolean('download')) {
            $response->header('Content-Disposition', 'attachment; filename="'.$shortLink->slug.'-qr.svg"');
        }

        return $response;
    }

    /**
     * Serialize a short link for the dashboard.
     *
     * @return array<string, mixed>
     */
    private function linkPayload(ShortLink $link, int $clicksWeek = 0, int $visitorsWeek = 0): array
    {
        return [
            'id' => $link->id,
            'title' => $link->title,
            'slug' => $link->slug,
            'short_url' => $link->shortUrl(),
            'destination' => $link->destination,
            'mobile_destination' => $link->mobile_destination,
            'protected' => $link->hasPassword(),
            'expires_at' => $link->expires_at?->toIso8601String(),
            'max_clicks' => $link->max_clicks,
            'clicks' => (int) $link->clicks,
            'clicks_week' => $clicksWeek,
            'visitors_week' => $visitorsWeek,
            'status' => $link->status(),
            'is_active' => $link->is_active,
            'last_clicked_at' => $link->last_clicked_at?->toIso8601String(),
            'created_at' => $link->created_at?->toIso8601String(),
            'utm' => [
                'source' => $link->utm_source,
                'medium' => $link->utm_medium,
                'campaign' => $link->utm_campaign,
                'term' => $link->utm_term,
                'content' => $link->utm_content,
            ],
        ];
    }

    /**
     * Return the submitted slug, or build a fresh one.
     */
    private function slug(mixed $requested): string
    {
        return filled($requested)
            ? strtolower(trim((string) $requested))
            : ShortLink::generateSlug();
    }

    /**
     * Keep one user's short links out of another user's screens.
     */
    private function ensureOwner(Request $request, ShortLink $shortLink): void
    {
        abort_unless($request->user()?->id === $shortLink->user_id, 403);
    }

    /**
     * Determine whether this session already unlocked the link.
     */
    private function unlocked(Request $request, ShortLink $shortLink): bool
    {
        return $request->hasSession()
            && $request->session()->get($this->sessionKey($shortLink)) === true;
    }

    /**
     * The session key that remembers an unlocked short link.
     */
    private function sessionKey(ShortLink $shortLink): string
    {
        return 'short-link-unlocked:'.$shortLink->id;
    }

    /**
     * Reduce a referrer to a readable source name.
     */
    private function referrerHost(string $referrer): string
    {
        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return (string) __('Direct / unknown');
        }

        return preg_replace('/^www\./', '', strtolower($host)) ?: $host;
    }

    /**
     * The signed-in user, or a 403 for guests.
     */
    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
