<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\LinkVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LinkVariantController extends Controller
{
    /**
     * The maximum number of variants a link can be tested with.
     */
    private const MAX_VARIANTS = 4;

    /**
     * Add a variant to an A/B tested link.
     */
    public function store(Request $request, Link $link): RedirectResponse
    {
        $this->authorizeEdit($request, $link);

        if ($link->variants()->count() >= self::MAX_VARIANTS) {
            return back()->withErrors([
                'label' => __('A link can have at most :count variants.', ['count' => self::MAX_VARIANTS]),
            ]);
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $link->variants()->create([
            'label' => $data['label'],
            'url' => $data['url'],
            'weight' => (int) ($data['weight'] ?? LinkVariant::DEFAULT_WEIGHT),
            'is_active' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variant added.')]);

        return back();
    }

    /**
     * Update a variant of a link.
     */
    public function update(Request $request, Link $link, LinkVariant $variant): RedirectResponse
    {
        $this->authorizeEdit($request, $link);

        abort_unless($variant->link_id === $link->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'weight' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $variant->update([
            'label' => $data['label'],
            'url' => $data['url'],
            ...(isset($data['weight']) ? ['weight' => (int) $data['weight']] : []),
            ...(isset($data['is_active']) ? ['is_active' => (bool) $data['is_active']] : []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variant updated.')]);

        return back();
    }

    /**
     * Remove a variant from a link.
     */
    public function destroy(Request $request, Link $link, LinkVariant $variant): RedirectResponse
    {
        $this->authorizeEdit($request, $link);

        abort_unless($variant->link_id === $link->id, 404);

        $variant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variant removed.')]);

        return back();
    }

    /**
     * Only owners and editors of the page may change its variants.
     */
    private function authorizeEdit(Request $request, Link $link): void
    {
        $user = $request->user();

        abort_unless($user !== null && $link->profile->canEditBio($user), 403);
    }
}
