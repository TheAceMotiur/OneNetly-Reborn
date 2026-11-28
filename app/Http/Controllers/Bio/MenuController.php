<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuOption;
use App\Models\MenuOptionGroup;
use App\Models\Profile;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    /**
     * Show the menu builder for one of the user's restaurants.
     */
    public function index(Request $request, Profile $profile): Response
    {
        $this->authorizeProfile($request, $profile);

        $profile->load(['menuCategories.items.optionGroups.options']);

        return Inertia::render('menu/Index', [
            'categories' => $profile->menuCategories->map(fn (MenuCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'daypart' => $category->daypart,
                'is_active' => $category->is_active,
                'items' => $category->items->map(fn (MenuItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => $item->price,
                    'sale_price' => $item->sale_price,
                    'sale_starts_at' => $item->sale_starts_at,
                    'sale_ends_at' => $item->sale_ends_at,
                    'is_on_sale' => $item->isOnSale(),
                    'dietary' => $item->dietary,
                    'allergens' => $item->allergens ?? [],
                    'image_url' => $item->image_url,
                    'is_available' => $item->is_available,
                    'is_featured' => $item->is_featured,
                    'option_groups' => $item->optionGroupsPayload(),
                ])->all(),
            ])->all(),
            'settings' => [
                'ordering_enabled' => $profile->menu_ordering_enabled,
                'currency' => $profile->menu_currency,
            ],
            'coupons' => $profile->coupons()->latest('id')->get()->map(fn ($coupon): array => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'label' => $coupon->label(),
                'type' => $coupon->type,
                'value' => $coupon->value,
                'used_count' => $coupon->used_count,
                'max_uses' => $coupon->max_uses,
                'expires_at' => $coupon->expires_at?->toDateString(),
                'is_active' => $coupon->is_active,
            ])->all(),
            'currencies' => Profile::MENU_CURRENCIES,
            'dayparts' => MenuCategory::DAYPARTS,
            'allergens' => Profile::ALLERGENS,
            'restaurant' => [
                'username' => $profile->username,
                'name' => $profile->restaurantName(),
            ],
            'restaurants' => $request->user()->accessibleRestaurants()
                ->map(fn (Profile $restaurant): array => [
                    'username' => $restaurant->username,
                    'name' => $restaurant->restaurantName(),
                ])
                ->all(),
            'baseUrl' => route('restaurant.menu', $profile),
            'publicUrl' => route('restaurant.show', $profile->username),
            'qrUrl' => route('restaurant.qr', $profile),
            'ordersUrl' => route('restaurant.orders', $profile),
        ]);
    }

    /**
     * Update the ordering settings for one restaurant.
     */
    public function updateSettings(Request $request, Profile $profile): RedirectResponse
    {
        $this->authorizeProfile($request, $profile);

        $data = $request->validate([
            'ordering_enabled' => ['required', 'boolean'],
            'currency' => ['required', 'string', Rule::in(array_keys(Profile::MENU_CURRENCIES))],
        ]);

        $profile->update([
            'menu_ordering_enabled' => $data['ordering_enabled'],
            'menu_currency' => $data['currency'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu settings updated.')]);

        return back();
    }

    /**
     * Add a menu section.
     */
    public function storeCategory(Request $request, Profile $profile): RedirectResponse
    {
        $this->authorizeProfile($request, $profile);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'daypart' => ['nullable', 'string', Rule::in(array_keys(MenuCategory::DAYPARTS))],
        ]);

        $profile->menuCategories()->create([
            ...$data,
            'position' => (int) ($profile->menuCategories()->max('position') ?? -1) + 1,
            'is_active' => true,
        ]);

        app(AuditLogger::class)->log($profile, 'menu.section_created', __('Menu section :name created.', [
            'name' => $data['name'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu section added.')]);

        return back();
    }

    /**
     * Update a menu section.
     */
    public function updateCategory(Request $request, Profile $profile, MenuCategory $category): RedirectResponse
    {
        $this->authorizeCategory($request, $profile, $category);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'daypart' => ['sometimes', 'string', Rule::in(array_keys(MenuCategory::DAYPARTS))],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($data);

        app(AuditLogger::class)->log($profile, 'menu.section_updated', __('Menu section :name updated.', [
            'name' => $category->name,
        ]), $category);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu section updated.')]);

        return back();
    }

    /**
     * Delete a menu section and its items.
     */
    public function destroyCategory(Request $request, Profile $profile, MenuCategory $category): RedirectResponse
    {
        $this->authorizeCategory($request, $profile, $category);

        $category->items->each(fn (MenuItem $item) => $this->deleteImage($item));

        $name = $category->name;

        $category->delete();

        app(AuditLogger::class)->log($profile, 'menu.section_deleted', __('Menu section :name deleted.', [
            'name' => $name,
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu section deleted.')]);

        return back();
    }

    /**
     * Add an item to a menu section.
     */
    public function storeItem(Request $request, Profile $profile, MenuCategory $category): RedirectResponse
    {
        $this->authorizeCategory($request, $profile, $category);

        $data = $this->validateItem($request);
        $data['image_path'] = $this->storeImage($request->file('image'));
        $data['allergens'] = $this->allergenList($data['allergens'] ?? null);

        $category->items()->create([
            ...$data,
            'position' => (int) ($category->items()->max('position') ?? -1) + 1,
            'is_available' => $request->boolean('is_available', true),
            'is_featured' => $request->boolean('is_featured'),
        ]);

        app(AuditLogger::class)->log($profile, 'menu.item_created', __('Menu item :name added.', [
            'name' => $data['name'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu item added.')]);

        return back();
    }

    /**
     * Update an item on the menu.
     */
    public function updateItem(Request $request, Profile $profile, MenuItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $profile, $item);

        $data = $this->validateItem($request);

        if ($request->has('is_available')) {
            $data['is_available'] = $request->boolean('is_available');
        }

        if ($request->has('is_featured')) {
            $data['is_featured'] = $request->boolean('is_featured');
        }

        if ($request->has('allergens')) {
            $data['allergens'] = $this->allergenList($data['allergens'] ?? null);
        }

        if ($request->boolean('remove_image')) {
            $this->deleteImage($item);
            $data['image_path'] = null;
        }

        if ($request->file('image') instanceof UploadedFile) {
            $this->deleteImage($item);
            $data['image_path'] = $this->storeImage($request->file('image'));
        }

        $item->update($data);

        app(AuditLogger::class)->log($profile, 'menu.item_updated', __('Menu item :name updated.', [
            'name' => $item->name,
        ]), $item);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu item updated.')]);

        return back();
    }

    /**
     * Remove an item from the menu.
     */
    public function destroyItem(Request $request, Profile $profile, MenuItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $profile, $item);

        $this->deleteImage($item);

        $name = $item->name;

        $item->delete();

        app(AuditLogger::class)->log($profile, 'menu.item_deleted', __('Menu item :name deleted.', [
            'name' => $name,
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu item deleted.')]);

        return back();
    }

    /**
     * Add an option group (size, extras, milk) to an item.
     */
    public function storeOptionGroup(Request $request, Profile $profile, MenuItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $profile, $item);

        $data = $this->validateOptionGroup($request);

        $item->optionGroups()->create([
            ...$data,
            'position' => (int) ($item->optionGroups()->max('position') ?? -1) + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option group added.')]);

        return back();
    }

    /**
     * Update an option group.
     */
    public function updateOptionGroup(Request $request, Profile $profile, MenuItem $item, MenuOptionGroup $group): RedirectResponse
    {
        $this->authorizeGroup($request, $profile, $item, $group);

        $group->update($this->validateOptionGroup($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option group updated.')]);

        return back();
    }

    /**
     * Delete an option group and its choices.
     */
    public function destroyOptionGroup(Request $request, Profile $profile, MenuItem $item, MenuOptionGroup $group): RedirectResponse
    {
        $this->authorizeGroup($request, $profile, $item, $group);

        $group->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option group deleted.')]);

        return back();
    }

    /**
     * Add a choice to an option group.
     */
    public function storeOption(Request $request, Profile $profile, MenuItem $item, MenuOptionGroup $group): RedirectResponse
    {
        $this->authorizeGroup($request, $profile, $item, $group);

        $data = $this->validateOption($request);

        $group->options()->create([
            ...$data,
            'position' => (int) ($group->options()->max('position') ?? -1) + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option added.')]);

        return back();
    }

    /**
     * Update a choice in an option group.
     */
    public function updateOption(Request $request, Profile $profile, MenuItem $item, MenuOptionGroup $group, MenuOption $option): RedirectResponse
    {
        $this->authorizeOption($request, $profile, $item, $group, $option);

        $data = $this->validateOption($request);

        if ($request->has('is_available')) {
            $data['is_available'] = $request->boolean('is_available');
        }

        $option->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option updated.')]);

        return back();
    }

    /**
     * Delete a choice from an option group.
     */
    public function destroyOption(Request $request, Profile $profile, MenuItem $item, MenuOptionGroup $group, MenuOption $option): RedirectResponse
    {
        $this->authorizeOption($request, $profile, $item, $group, $option);

        $option->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option deleted.')]);

        return back();
    }

    /**
     * Validate an option group payload.
     *
     * @return array<string, mixed>
     */
    private function validateOptionGroup(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'is_required' => ['sometimes', 'boolean'],
            'allow_multiple' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * Validate an option payload.
     *
     * @return array<string, mixed>
     */
    private function validateOption(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'price' => ['nullable', 'string', 'max:30'],
            'is_available' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * Validate the shared item fields.
     *
     * @return array<string, mixed>
     */
    private function validateItem(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['nullable', 'string', 'max:30'],
            'sale_price' => ['nullable', 'string', 'max:30'],
            'sale_starts_at' => ['nullable', 'date_format:H:i'],
            'sale_ends_at' => ['nullable', 'date_format:H:i'],
            'dietary' => ['nullable', 'string', 'max:80'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => ['string', Rule::in(array_keys(Profile::ALLERGENS))],
            'is_available' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        unset($data['image'], $data['remove_image']);

        return $data;
    }

    /**
     * Keep only known allergens, storing an empty selection as null.
     *
     * @param  array<int, string>|null  $allergens
     * @return array<int, string>|null
     */
    private function allergenList(?array $allergens): ?array
    {
        $known = array_values(array_intersect($allergens ?? [], array_keys(Profile::ALLERGENS)));

        return $known === [] ? null : $known;
    }

    /**
     * Store an uploaded menu image on the public disk.
     */
    private function storeImage(?UploadedFile $image): ?string
    {
        if (! $image instanceof UploadedFile) {
            return null;
        }

        $path = $image->store('menu-items', 'public');

        return $path === false ? null : $path;
    }

    /**
     * Delete the image belonging to a menu item.
     */
    private function deleteImage(MenuItem $item): void
    {
        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
    }

    /**
     * Ensure the restaurant belongs to the signed-in user.
     */
    private function authorizeProfile(Request $request, Profile $profile): void
    {
        abort_unless($profile->canManage($request->user()), 403);
    }

    /**
     * Ensure the section belongs to the restaurant in the URL.
     */
    private function authorizeCategory(Request $request, Profile $profile, MenuCategory $category): void
    {
        $this->authorizeProfile($request, $profile);

        abort_unless($category->profile_id === $profile->id, 403);
    }

    /**
     * Ensure the item belongs to the restaurant in the URL.
     */
    private function authorizeItem(Request $request, Profile $profile, MenuItem $item): void
    {
        $this->authorizeProfile($request, $profile);

        abort_unless($item->category->profile_id === $profile->id, 403);
    }

    /**
     * Ensure the option group belongs to the item in the URL.
     */
    private function authorizeGroup(Request $request, Profile $profile, MenuItem $item, MenuOptionGroup $group): void
    {
        $this->authorizeItem($request, $profile, $item);

        abort_unless($group->menu_item_id === $item->id, 404);
    }

    /**
     * Ensure the option belongs to the group and item in the URL.
     */
    private function authorizeOption(Request $request, Profile $profile, MenuItem $item, MenuOptionGroup $group, MenuOption $option): void
    {
        $this->authorizeGroup($request, $profile, $item, $group);

        abort_unless($option->menu_option_group_id === $group->id, 404);
    }
}
