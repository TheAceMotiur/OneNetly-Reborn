<?php

namespace App\Http\Controllers;

use App\Http\Requests\Resume\StoreResumeRequest;
use App\Http\Requests\Resume\UpdateResumeRequest;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ResumeController extends Controller
{
    /**
     * Show the resumes the user has built.
     */
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('resumes/Index', [
            'resumes' => $user->resumes()->with('user')->get()
                ->map(fn (Resume $resume): array => $resume->payload())
                ->all(),
            'templates' => $this->templateCatalog(),
            'accents' => Resume::ACCENTS,
            'watermark' => ! $user->canRemoveBranding(),
        ]);
    }

    /**
     * Show the template gallery with a live preview of each layout.
     */
    public function templates(Request $request): Response
    {
        return Inertia::render('resumes/Templates', [
            'templates' => $this->templateCatalog(),
            'sample' => Resume::sampleContent(),
            'accents' => Resume::ACCENTS,
            'defaultAccent' => Resume::ACCENTS['indigo'],
            'total' => $this->user($request)->resumes()->count(),
        ]);
    }

    /**
     * Create a resume from the chosen template.
     */
    public function store(StoreResumeRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validated();

        $resume = $user->resumes()->create([
            'title' => $data['title'],
            'template' => $data['template'],
            'accent' => $data['accent'] ?? 'indigo',
            'font' => $data['font'] ?? 'sans',
            'slug' => Resume::uniqueSlug($data['title']),
            'content' => Resume::starterContent($user->name, $user->email),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Resume created. Add your experience to bring it to life.'),
        ]);

        return to_route('resumes.edit', $resume);
    }

    /**
     * Show the resume builder.
     */
    public function edit(Request $request, Resume $resume): Response
    {
        $this->ensureOwner($request, $resume);

        return Inertia::render('resumes/Edit', [
            'resume' => $resume->payload(),
            'templates' => $this->templateCatalog(),
            'accents' => Resume::ACCENTS,
            'fonts' => Resume::FONTS,
        ]);
    }

    /**
     * Save the resume content and styling.
     */
    public function update(UpdateResumeRequest $request, Resume $resume): RedirectResponse
    {
        $data = $request->validated();

        $resume->update([
            'title' => $data['title'],
            'template' => $data['template'],
            'accent' => $data['accent'],
            'font' => $data['font'],
            'content' => Resume::normaliseContent($data['content']),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Resume saved.'),
        ]);

        return back();
    }

    /**
     * Publish or hide the public share link.
     */
    public function publish(Request $request, Resume $resume): RedirectResponse
    {
        $this->ensureOwner($request, $resume);

        $resume->update([
            'is_public' => ! $resume->is_public,
            'slug' => $resume->slug !== '' ? $resume->slug : Resume::uniqueSlug($resume->title),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $resume->is_public
                ? __('Your resume is now shared at a private link.')
                : __('Your resume is private again.'),
        ]);

        return back();
    }

    /**
     * Copy an existing resume as a starting point for a tailored version.
     */
    public function duplicate(Request $request, Resume $resume): RedirectResponse
    {
        $this->ensureOwner($request, $resume);

        $copy = $this->user($request)->resumes()->create([
            'title' => Str::limit($resume->title.' (copy)', 120),
            'template' => $resume->template,
            'accent' => $resume->accent,
            'font' => $resume->font,
            'slug' => Resume::uniqueSlug($resume->title.' copy'),
            'content' => $resume->structuredContent(),
            'is_public' => false,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Resume duplicated.'),
        ]);

        return to_route('resumes.edit', $copy);
    }

    /**
     * Delete the resume.
     */
    public function destroy(Request $request, Resume $resume): RedirectResponse
    {
        $this->ensureOwner($request, $resume);

        $resume->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Resume deleted.'),
        ]);

        return to_route('resumes.index');
    }

    /**
     * Show the resume on a clean page ready for printing or saving as PDF.
     */
    public function printView(Request $request, Resume $resume): Response
    {
        $this->ensureOwner($request, $resume);

        return Inertia::render('resumes/Print', [
            'resume' => $resume->payload(),
            'backUrl' => route('resumes.edit', $resume),
        ]);
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

    /**
     * Keep one user's resumes out of another user's screens.
     */
    private function ensureOwner(Request $request, Resume $resume): void
    {
        abort_unless($request->user()?->id === $resume->user_id, 403);
    }

    /**
     * The template catalogue as a list the frontend can iterate.
     *
     * @return list<array{key: string, name: string, description: string, style: string, ats: bool, tags: list<string>}>
     */
    private function templateCatalog(): array
    {
        $templates = [];

        foreach (Resume::TEMPLATES as $key => $template) {
            $templates[] = ['key' => $key, ...$template];
        }

        return $templates;
    }
}
