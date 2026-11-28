<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bio\StoreSocialLinkRequest;
use App\Http\Requests\Bio\UpdateSocialLinkRequest;
use App\Models\SocialLink;
use App\Services\BioPageAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SocialLinkController extends Controller
{
    public function __construct(private readonly BioPageAccess $pages) {}

    /**
     * Add a social icon to the user's bio page.
     */
    public function store(StoreSocialLinkRequest $request): RedirectResponse
    {
        $profile = $this->pages->resolve($request, write: true);

        $profile->socials()->create([
            ...$request->validated(),
            'position' => (int) ($profile->socials()->max('position') ?? -1) + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Social link added.')]);

        return back();
    }

    /**
     * Update an existing social icon on the user's bio page.
     */
    public function update(UpdateSocialLinkRequest $request, SocialLink $social): RedirectResponse
    {
        abort_unless(
            $request->user() !== null && $social->profile->canEditBio($request->user()),
            403,
        );

        $social->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Social link updated.')]);

        return back();
    }

    /**
     * Remove a social icon from the user's bio page.
     */
    public function destroy(Request $request, SocialLink $social): RedirectResponse
    {
        abort_unless(
            $request->user() !== null && $social->profile->canEditBio($request->user()),
            403,
        );

        $social->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Social link removed.')]);

        return back();
    }
}
