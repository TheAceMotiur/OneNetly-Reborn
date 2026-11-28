<?php

namespace App\Http\Controllers;

use App\Http\Requests\Widget\StoreWidgetRequest;
use App\Http\Requests\Widget\UpdateWidgetRequest;
use App\Models\User;
use App\Models\Widget;
use App\Support\Widgets\WidgetCatalog;
use App\Support\Widgets\WidgetSnippets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WidgetController extends Controller
{
    /**
     * Serve the hosted embed script that a widget's script tag loads.
     */
    public function embed(string $key): HttpResponse
    {
        $widget = Widget::query()
            ->with('user')
            ->where('public_key', $key)
            ->firstOrFail();

        $javascript = WidgetSnippets::embedSource(
            $widget->type,
            $widget->configuration(),
            ! ($widget->user?->canRemoveBranding() ?? false),
            (string) $widget->public_key,
        );

        return response($javascript)
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Show the widget gallery and the widgets the user has saved.
     */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $requested = $request->string('category')->toString();
        $category = array_key_exists($requested, WidgetCatalog::CATEGORIES) ? $requested : null;

        $widgets = $user->widgets()
            ->with('user')
            ->latest('updated_at')
            ->get()
            ->filter(fn (Widget $widget): bool => $category === null
                || ($widget->definition()['category'] ?? null) === $category)
            ->map(fn (Widget $widget): array => $widget->payload())
            ->values()
            ->all();

        return Inertia::render('widgets/Index', [
            'widgets' => $widgets,
            'catalog' => WidgetCatalog::catalog($category),
            'categories' => WidgetCatalog::CATEGORIES,
            'activeCategory' => $category,
            'total' => $user->widgets()->count(),
            'watermark' => ! $user->canRemoveBranding(),
        ]);
    }

    /**
     * Create a widget from the catalogue with its default settings.
     */
    public function store(StoreWidgetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $type = (string) $data['type'];
        $definition = WidgetCatalog::definition($type);

        $widget = $this->user($request)->widgets()->create([
            'type' => $type,
            'name' => $data['name'] ?? (string) ($definition['name'] ?? $type),
            'config' => WidgetCatalog::defaults($type),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Widget added. Adjust the settings and copy the code.'),
        ]);

        return to_route('widgets.edit', $widget);
    }

    /**
     * Show the configurator of one widget.
     */
    public function edit(Request $request, Widget $widget): Response
    {
        $this->ensureOwner($request, $widget);

        return Inertia::render('widgets/Edit', [
            'widget' => $widget->payload(),
            'definition' => $widget->definition(),
            'categories' => WidgetCatalog::CATEGORIES,
        ]);
    }

    /**
     * Save the widget settings.
     */
    public function update(UpdateWidgetRequest $request, Widget $widget): RedirectResponse
    {
        $data = $request->validated();

        $widget->update([
            'name' => $data['name'],
            'config' => WidgetCatalog::normalise(
                $widget->type,
                is_array($data['config'] ?? null) ? $data['config'] : [],
            ),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Widget saved. The embed code below is up to date.'),
        ]);

        return back();
    }

    /**
     * Render the embed code and preview for the current (unsaved) settings.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(WidgetCatalog::all()))],
            'config' => ['nullable', 'array'],
        ]);

        $type = (string) $data['type'];
        $config = WidgetCatalog::normalise(
            $type,
            is_array($data['config'] ?? null) ? $data['config'] : [],
        );

        $credit = ! $this->user($request)->canRemoveBranding();

        return response()->json([
            'preview' => WidgetSnippets::preview($type, $config, $credit),
            'watermark' => $credit,
        ]);
    }

    /**
     * Remove the widget.
     */
    public function destroy(Request $request, Widget $widget): RedirectResponse
    {
        $this->ensureOwner($request, $widget);

        $widget->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Widget deleted.'),
        ]);

        return to_route('widgets.index');
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
     * Keep one user's widgets out of another user's screens.
     */
    private function ensureOwner(Request $request, Widget $widget): void
    {
        abort_unless($request->user()?->id === $widget->user_id, 403);
    }
}
