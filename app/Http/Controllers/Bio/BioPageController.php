<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bio\UpdateBioPageRequest;
use App\Models\Link;
use App\Models\Profile;
use App\Models\ProfileBlock;
use App\Models\SocialLink;
use App\Models\User;
use App\Services\BioPageAccess;
use App\Services\DomainVerifier;
use App\Services\ProfileAccess;
use App\Services\ProfileViewTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BioPageController extends Controller
{
    public function __construct(
        private readonly ProfileViewTracker $viewTracker,
        private readonly ProfileAccess $access,
        private readonly DomainVerifier $domainVerifier,
        private readonly BioPageAccess $pages,
    ) {}

    /**
     * Show the form for editing the user's bio page.
     */
    public function edit(Request $request): Response
    {
        $profile = $this->pages->resolve($request)->load(['socials', 'collaborators']);
        $user = $request->user();

        return Inertia::render('bio/Edit', [
            'profile' => [
                'username' => $profile->username,
                'custom_domain' => $profile->custom_domain,
                'custom_domain_verified' => $profile->custom_domain_verified_at !== null,
                'custom_domain_record' => $this->domainVerifier->recordValue($profile),
                'display_name' => $profile->display_name,
                'meta_title' => $profile->meta_title,
                'meta_description' => $profile->meta_description,
                'bio' => $profile->bio,
                'theme' => $profile->theme,
                'template' => $profile->template,
                'business_type' => $profile->business_type,
                'company' => $profile->company,
                'job_title' => $profile->job_title,
                'contact_email' => $profile->contact_email,
                'phone' => $profile->phone,
                'contact_website' => $profile->contact_website,
                'address' => $profile->address,
                'is_published' => $profile->is_published,
                'show_contact_form' => $profile->show_contact_form,
                'has_password' => filled($profile->access_password),
                'avatar_url' => $profile->avatar_url,
                'public_url' => route('bio.show', $profile->username),
            ],
            'canUseCustomDomain' => setting('custom_domains_enabled') && $user?->hasPlanFeature('has_custom_domain'),
            'customDomainsEnabled' => (bool) setting('custom_domains_enabled'),
            'themes' => Profile::THEMES,
            'templates' => Profile::TEMPLATES,
            'businessTypes' => Profile::BUSINESS_TYPES,
            'socials' => $this->socialsPayload($profile),
            'platforms' => SocialLink::PLATFORMS,
            'pages' => $this->pages->pagesFor($user)->all(),
            'activePage' => $profile->username,
            'canEdit' => $user !== null && $profile->canEditBio($user),
            'canManageCollaborators' => $profile->user_id === $user?->id,
            'collaborators' => $profile->user_id === $user?->id
                ? $profile->collaborators->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $profile->roleFor($member) ?? 'viewer',
                ])->all()
                : [],
        ]);
    }

    /**
     * Update the user's bio page settings.
     */
    public function update(UpdateBioPageRequest $request): RedirectResponse
    {
        $profile = $this->pages->resolve($request, write: true);

        $data = $request->safe()->except(['avatar', 'remove_avatar', 'page_password', 'remove_page_password']);

        if ($request->boolean('remove_page_password')) {
            $data['access_password'] = null;
        } elseif (filled($request->input('page_password'))) {
            $data['access_password'] = Hash::make((string) $request->input('page_password'));
        }

        if (! setting('custom_domains_enabled') || ! $request->user()?->hasPlanFeature('has_custom_domain')) {
            $data['custom_domain'] = $profile->custom_domain;
        } elseif (array_key_exists('custom_domain', $data)) {
            $data['custom_domain'] = filled($data['custom_domain'])
                ? strtolower(trim((string) $data['custom_domain']))
                : null;

            if ($data['custom_domain'] !== $profile->custom_domain || blank($profile->custom_domain_token)) {
                $data['custom_domain_token'] = $data['custom_domain'] !== null
                    ? Str::random(32)
                    : null;
                $data['custom_domain_verified_at'] = null;
            }
        }

        if ($request->boolean('remove_avatar') && $profile->avatar_path) {
            Storage::disk('public')->delete($profile->avatar_path);

            $data['avatar_path'] = null;
        }

        if ($request->file('avatar') instanceof UploadedFile) {
            if ($profile->avatar_path) {
                Storage::disk('public')->delete($profile->avatar_path);
            }

            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $profile->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Your bio page has been updated.'),
        ]);

        return to_route('bio.edit', $profile->user_id === $request->user()?->id
            ? []
            : ['page' => $profile->username]);
    }

    /**
     * Check the DNS TXT record for a custom domain.
     */
    public function verifyDomain(Request $request): RedirectResponse
    {
        $profile = Profile::forUser($request->user());

        if (blank($profile->custom_domain) || blank($profile->custom_domain_token)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Save a custom domain before verifying it.'),
            ]);

            return back();
        }

        if (! $this->domainVerifier->verify($profile)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('The verification TXT record was not found yet. DNS changes can take a few minutes.'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':domain is verified.', ['domain' => $profile->custom_domain]),
        ]);

        return back();
    }

    /**
     * Display the public bio page for the given username.
     */
    public function show(Request $request, string $username): Response|RedirectResponse
    {
        $profile = Profile::query()
            ->where('username', $username)
            ->where('is_published', true)
            ->with([
                'user:id,name',
                'links' => fn ($query) => $query->live(),
                'socials',
                'blocks' => fn ($query) => $query->where('is_active', true),
            ])
            ->firstOrFail();

        if ($profile->isRestaurant()) {
            return redirect()->route('restaurant.show', $profile->username);
        }

        $displayName = $profile->display_name ?: $profile->user->name;

        $this->viewTracker->record($request, $profile);

        if ($this->access->requiresPassword($profile) && ! $this->access->isUnlocked($request, $profile)) {
            return Inertia::render('bio/Password', [
                'profile' => [
                    'username' => $profile->username,
                    'display_name' => $displayName,
                    'avatar_url' => $profile->avatar_url,
                    'theme' => $profile->theme,
                ],
            ]);
        }

        $description = $profile->bio ?: __('All the links from :name in one place.', ['name' => $displayName]);

        return Inertia::render('bio/Show', [
            'profile' => [
                'username' => $profile->username,
                'display_name' => $displayName,
                'bio' => $profile->bio,
                'avatar_url' => $profile->avatar_url,
                'theme' => $profile->theme,
                'template' => $profile->template,
                'share_url' => route('bio.show', $profile->username),
                'share_image' => $profile->avatar_url ? url($profile->avatar_url) : null,
                'seo_title' => $profile->meta_title ?: $displayName,
                'seo_description' => $profile->meta_description ?: $description,
                'business_type' => $profile->businessTypeLabel(),
                'contact_enabled' => $profile->show_contact_form,
                'has_vcard' => $profile->hasVCardDetails(),
                'vcard_url' => route('bio.vcard', $profile->username),
                'show_branding' => ! $profile->user->canRemoveBranding(),
            ],
            'links' => $profile->links->map(fn (Link $link): array => [
                'id' => $link->id,
                'title' => $link->title,
                'url' => route('links.redirect', $link),
                'icon' => $link->resolvedIcon(),
            ])->all(),
            'blocks' => $profile->blocks->map(fn (ProfileBlock $block): array => [
                'id' => $block->id,
                'type' => $block->type,
                'content' => $block->content,
                'media_url' => $block->media_url,
                'embed_url' => $block->embedUrl(),
            ])->all(),
            'socials' => $this->socialsPayload($profile),
        ]);
    }

    /**
     * Unlock a password-protected page for the current session.
     */
    public function unlock(Request $request, string $username): RedirectResponse
    {
        $profile = Profile::query()
            ->where('username', $username)
            ->where('is_published', true)
            ->firstOrFail();

        abort_unless($this->access->requiresPassword($profile), 404);

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check((string) $request->input('password'), (string) $profile->access_password)) {
            throw ValidationException::withMessages([
                'password' => __('The password you entered is incorrect.'),
            ]);
        }

        $this->access->unlock($request, $profile);

        return redirect()->to(route('bio.show', $profile->username, false));
    }

    /**
     * Serialize the social links of a profile for the frontend.
     *
     * @return array<int, array{id: int, platform: string, label: string, url: string, href: string}>
     */
    private function socialsPayload(Profile $profile): array
    {
        return $profile->socials->map(fn (SocialLink $social): array => [
            'id' => $social->id,
            'platform' => $social->platform,
            'label' => $social->label(),
            'url' => $social->url,
            'href' => $social->href(),
        ])->values()->all();
    }
}
