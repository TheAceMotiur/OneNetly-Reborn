<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\ProfileBlock;
use App\Services\BioPageAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BlockController extends Controller
{
    public function __construct(private readonly BioPageAccess $pages) {}

    /**
     * Show the rich blocks of a bio page.
     */
    public function index(Request $request): Response
    {
        $profile = $this->pages->resolve($request)->load('blocks');
        $user = $request->user();

        return Inertia::render('bio/Blocks', [
            'blocks' => $profile->blocks->map(fn (ProfileBlock $block): array => [
                'id' => $block->id,
                'type' => $block->type,
                'content' => $block->content,
                'media_url' => $block->media_url,
                'embed_url' => $block->embedUrl(),
                'is_active' => $block->is_active,
            ])->all(),
            'types' => ProfileBlock::TYPES,
            'pages' => $this->pages->pagesFor($user)->all(),
            'activePage' => $profile->username,
            'publicUrl' => route('bio.show', $profile->username),
            'canEdit' => $user !== null && $profile->canEditBio($user),
        ]);
    }

    /**
     * Add a block to a bio page.
     */
    public function store(Request $request): RedirectResponse
    {
        $profile = $this->pages->resolve($request, write: true);

        $data = $this->validateBlock($request);

        $profile->blocks()->create([
            ...$data,
            'position' => (int) ($profile->blocks()->max('position') ?? -1) + 1,
            'is_active' => $request->boolean('is_active', true),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Block added.')]);

        return back();
    }

    /**
     * Update one block on a bio page.
     */
    public function update(Request $request, ProfileBlock $block): RedirectResponse
    {
        $profile = $this->pages->resolve($request, write: true);

        abort_unless($block->profile_id === $profile->id, 404);

        $block->update([
            ...$this->validateBlock($request),
            'is_active' => $request->boolean('is_active', $block->is_active),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Block updated.')]);

        return back();
    }

    /**
     * Remove a block from a bio page.
     */
    public function destroy(Request $request, ProfileBlock $block): RedirectResponse
    {
        $profile = $this->pages->resolve($request, write: true);

        abort_unless($block->profile_id === $profile->id, 404);

        $block->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Block deleted.')]);

        return back();
    }

    /**
     * Persist a new block order.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $profile = $this->pages->resolve($request, write: true);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        /** @var list<int> $ids */
        $ids = array_map(intval(...), $data['ids']);

        $blocks = $profile->blocks()->whereIn('id', $ids)->get()->keyBy('id');

        abort_if($blocks->count() !== count(array_unique($ids)), 403);

        foreach ($ids as $position => $id) {
            $blocks[$id]->update(['position' => $position]);
        }

        return back();
    }

    /**
     * Validate the content of a block.
     *
     * @return array{type: string, content: string|null, media_url: string|null}
     */
    private function validateBlock(Request $request): array
    {
        $type = (string) $request->input('type');

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(ProfileBlock::TYPES))],
            'content' => [
                Rule::requiredIf(fn (): bool => in_array($type, [ProfileBlock::TYPE_HEADING, ProfileBlock::TYPE_TEXT], true)),
                'nullable', 'string', 'max:2000',
            ],
            'media_url' => [
                Rule::requiredIf(fn (): bool => in_array($type, [ProfileBlock::TYPE_IMAGE, ProfileBlock::TYPE_VIDEO], true)),
                'nullable', 'string', 'max:2048', 'url:http,https',
            ],
        ]);

        return [
            'type' => $data['type'],
            'content' => $data['content'] ?? null,
            'media_url' => $data['media_url'] ?? null,
        ];
    }
}
