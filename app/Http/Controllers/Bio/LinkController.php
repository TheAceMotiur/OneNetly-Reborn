<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bio\ReorderLinksRequest;
use App\Http\Requests\Bio\StoreLinkRequest;
use App\Http\Requests\Bio\UpdateLinkRequest;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\LinkVariant;
use App\Services\BioPageAccess;
use App\Services\ProfileAccess;
use App\Support\VisitorDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LinkController extends Controller
{
    /**
     * The number of clicks recorded per visitor IP each minute.
     */
    private const CLICK_RECORD_LIMIT = 60;

    public function __construct(
        private readonly ProfileAccess $access,
        private readonly BioPageAccess $pages,
    ) {}

    /**
     * Show the links of the user's bio page.
     */
    public function index(Request $request): Response
    {
        $profile = $this->pages->resolve($request)->load(['links.variants']);
        $user = $request->user();

        $weekClicks = LinkClick::query()
            ->whereIn('link_id', $profile->links->pluck('id'))
            ->where('clicked_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('link_id, COUNT(*) as total')
            ->groupBy('link_id')
            ->pluck('total', 'link_id');

        $linkIds = $profile->links->pluck('id');

        $variantWeekClicks = LinkClick::query()
            ->whereIn('link_id', $linkIds)
            ->whereNotNull('variant_id')
            ->where('clicked_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('variant_id, COUNT(*) as total')
            ->groupBy('variant_id')
            ->pluck('total', 'variant_id');

        return Inertia::render('links/Index', [
            'links' => $profile->links->map(fn (Link $link): array => [
                'id' => $link->id,
                'title' => $link->title,
                'url' => $link->url,
                'icon' => $link->resolvedIcon(),
                'is_active' => $link->is_active,
                'status' => $link->status(),
                'clicks' => $link->clicks,
                'clicks_week' => (int) ($weekClicks[$link->id] ?? 0),
                'starts_at' => $link->starts_at?->toIso8601String(),
                'ends_at' => $link->ends_at?->toIso8601String(),
                'variants' => $link->variants->map(fn (LinkVariant $variant): array => [
                    'id' => $variant->id,
                    'label' => $variant->label,
                    'url' => $variant->url,
                    'weight' => $variant->weight,
                    'clicks' => $variant->clicks,
                    'clicks_week' => (int) ($variantWeekClicks[$variant->id] ?? 0),
                    'is_active' => $variant->is_active,
                ])->all(),
            ])->all(),
            'icons' => Link::ICONS,
            'stats' => [
                'total' => $profile->links->count(),
                'active' => $profile->links->filter->isLive()->count(),
                'clicks' => (int) $profile->links->sum('clicks'),
                'clicksWeek' => (int) $weekClicks->sum(),
            ],
            'publicUrl' => route('bio.show', $profile->username),
            'isPublished' => $profile->is_published,
            'pages' => $this->pages->pagesFor($user)->all(),
            'activePage' => $profile->username,
            'canEdit' => $user !== null && $profile->canEditBio($user),
            'limits' => [
                'used' => $profile->links->count(),
                'limit' => $profile->user->linkLimit(),
                'plan' => $profile->user->currentPlan()?->name,
            ],
        ]);
    }

    /**
     * Store a new link on the user's bio page.
     */
    public function store(StoreLinkRequest $request): RedirectResponse
    {
        $profile = $this->pages->resolve($request, write: true);
        $limit = $profile->user->linkLimit();

        if ($limit !== null && $profile->links()->count() >= $limit) {
            return back()->withErrors([
                'limit' => __('Your current plan allows :limit links. Upgrade to add more.', ['limit' => $limit]),
            ]);
        }

        $profile->links()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
            'position' => (int) ($profile->links()->max('position') ?? -1) + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link added.')]);

        return to_route('links.index', $profile->user_id === $request->user()?->id
            ? []
            : ['page' => $profile->username]);
    }

    /**
     * Update the given link.
     */
    public function update(UpdateLinkRequest $request, Link $link): RedirectResponse
    {
        $link->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link updated.')]);

        return back();
    }

    /**
     * Create a copy of the given link at the end of the page.
     */
    public function duplicate(Request $request, Link $link): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user !== null && $link->profile->canEditBio($user), 403);

        $profile = $link->profile;
        $limit = $profile->user->linkLimit();

        if ($limit !== null && $profile->links()->count() >= $limit) {
            $message = __('Your current plan allows :limit links. Upgrade to add more.', ['limit' => $limit]);

            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back()->withErrors(['limit' => $message]);
        }

        $profile->links()->create([
            'title' => Str::limit($link->title.' (copy)', 255),
            'url' => $link->url,
            'icon' => $link->icon,
            'is_active' => $link->is_active,
            'position' => (int) ($profile->links()->max('position') ?? -1) + 1,
            'starts_at' => $link->starts_at,
            'ends_at' => $link->ends_at,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link duplicated.')]);

        return back();
    }

    /**
     * Remove the given link.
     */
    public function destroy(Request $request, Link $link): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user !== null && $link->profile->canEditBio($user), 403);

        $link->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link deleted.')]);

        return back();
    }

    /**
     * Persist a new order for the links of the user's bio page.
     */
    public function reorder(ReorderLinksRequest $request): RedirectResponse
    {
        $profile = $this->pages->resolve($request, write: true);

        /** @var list<int> $ids */
        $ids = array_map(intval(...), $request->validated('ids'));

        $links = $profile->links()->whereIn('id', $ids)->get()->keyBy('id');

        abort_if($links->count() !== count(array_unique($ids)), 403);

        foreach ($ids as $position => $id) {
            $links[$id]->update(['position' => $position]);
        }

        return back();
    }

    /**
     * Record a click and send the visitor to the link destination.
     */
    public function redirect(Request $request, Link $link): RedirectResponse
    {
        abort_unless($link->profile->is_published && $link->isLive(), 404);

        if (! $this->access->isUnlocked($request, $link->profile)) {
            return redirect()->to(route('bio.show', $link->profile->username, false));
        }

        $variant = $this->pickVariant($link);

        if (VisitorDetector::isReal($request, $link->profile->user_id) && $this->withinClickRecordLimit($request)) {
            $referrer = $request->headers->get('referer');
            $agent = $request->userAgent();

            $link->clicks()->create([
                'variant_id' => $variant?->id,
                'clicked_at' => now(),
                'referrer' => $referrer ? Str::limit($referrer, 512, '') : null,
                'user_agent' => $agent ? Str::limit($agent, 512, '') : null,
            ]);

            $link->increment('clicks');

            $variant?->increment('clicks');
        }

        return redirect()->away($variant instanceof LinkVariant ? $variant->url : $link->url);
    }

    /**
     * Pick a variant for this visit using its relative weight.
     */
    private function pickVariant(Link $link): ?LinkVariant
    {
        $variants = $link->variants()->where('is_active', true)->get();

        if ($variants->isEmpty()) {
            return null;
        }

        $total = max(1, (int) $variants->sum(fn (LinkVariant $variant): int => max(1, $variant->weight)));
        $roll = random_int(1, $total);

        foreach ($variants as $variant) {
            $roll -= max(1, $variant->weight);

            if ($roll <= 0) {
                return $variant;
            }
        }

        return $variants->last();
    }

    /**
     * Keep recording bursts of clicks from one IP in check without ever
     * blocking the redirect itself.
     */
    private function withinClickRecordLimit(Request $request): bool
    {
        $key = 'link-clicks:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::CLICK_RECORD_LIMIT)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }
}
