<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Profile;
use App\Services\ProfileViewTracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicMenuController extends Controller
{
    public function __construct(private readonly ProfileViewTracker $viewTracker) {}

    /**
     * Display the public menu and ordering page of a restaurant.
     */
    public function __invoke(Request $request, string $username): Response
    {
        $profile = Profile::query()
            ->where('username', $username)
            ->where('is_published', true)
            ->with([
                'menuCategories' => fn ($query) => $query->where('is_active', true),
                'menuCategories.items' => fn ($query) => $query->where('is_available', true),
                'menuCategories.items.optionGroups.options',
            ])
            ->firstOrFail();

        $this->viewTracker->record($request, $profile);

        $table = mb_substr(trim((string) $request->query('table', '')), 0, 20);
        $approvedReviews = $profile->reviews()->where('is_approved', true)->latest()->limit(6)->get();

        return Inertia::render('restaurant/Show', [
            'restaurant' => [
                'username' => $profile->username,
                'name' => $profile->restaurantName(),
                'description' => $profile->bio,
                'address' => $profile->address,
                'phone' => $profile->phone,
                'ordering_enabled' => $profile->menu_ordering_enabled,
                'accepting_orders' => $profile->accepting_orders,
                'delivery_enabled' => $profile->delivery_enabled,
                'delivery_fee_cents' => $profile->delivery_fee_cents,
                'min_order_cents' => $profile->min_order_cents,
                'tax_rate' => $profile->tax_rate,
                'service_rate' => $profile->service_rate,
                'is_open_now' => $profile->isOpenNow(),
                'today_hours' => $profile->todayHoursLabel(),
                'currency' => $profile->menu_currency,
                'template' => $profile->restaurant_template,
                'table' => $table !== '' ? $table : null,
                'seo_title' => $profile->meta_title ?: $profile->restaurantName(),
                'show_branding' => ! $profile->user->canRemoveBranding(),
            ],
            'reviews' => $approvedReviews->map(fn ($review): array => [
                'id' => $review->id,
                'name' => $review->name,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at?->toFormattedDateString(),
            ])->all(),
            'review_stats' => [
                'count' => $profile->reviews()->where('is_approved', true)->count(),
                'average' => round((float) $profile->reviews()->where('is_approved', true)->avg('rating'), 1),
            ],
            'allergens' => Profile::ALLERGENS,
            'dayparts' => MenuCategory::DAYPARTS,
            'menu' => $profile->menuCategories
                ->map(fn (MenuCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'daypart' => $category->daypart,
                    'items' => $category->items->map(fn (MenuItem $item): array => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'price' => $item->isOnSale() ? $item->sale_price : $item->price,
                        'original_price' => $item->isOnSale() ? $item->price : null,
                        'is_on_sale' => $item->isOnSale(),
                        'dietary' => $item->dietary,
                        'allergens' => $item->allergens ?? [],
                        'image_url' => $item->image_url,
                        'price_cents' => $item->priceInCents(),
                        'is_featured' => $item->is_featured,
                        'option_groups' => $item->optionGroupsPayload(true),
                    ])->all(),
                ])
                ->filter(fn (array $category): bool => $category['items'] !== [])
                ->values()
                ->all(),
        ]);
    }
}
