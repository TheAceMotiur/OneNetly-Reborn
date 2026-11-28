<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Bio\BioPageController;
use App\Http\Controllers\Bio\BlockController;
use App\Http\Controllers\Bio\CollaboratorController;
use App\Http\Controllers\Bio\LinkController;
use App\Http\Controllers\Bio\LinkVariantController;
use App\Http\Controllers\Bio\MenuController;
use App\Http\Controllers\Bio\OrderController as PublicOrderController;
use App\Http\Controllers\Bio\ProfileContactController;
use App\Http\Controllers\Bio\PublicMenuController;
use App\Http\Controllers\Bio\PublicReservationController;
use App\Http\Controllers\Bio\PublicReviewController;
use App\Http\Controllers\Bio\PublicSubscriberController;
use App\Http\Controllers\Bio\PublicUnsubscribeController;
use App\Http\Controllers\Bio\QrCodeController;
use App\Http\Controllers\Bio\ServiceRequestController;
use App\Http\Controllers\Bio\SocialLinkController;
use App\Http\Controllers\Bio\TrackOrderController;
use App\Http\Controllers\Bio\VCardController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DeliveryZoneController;
use App\Http\Controllers\DeveloperConsoleController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TextBeeWebhookController;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('bio', [BioPageController::class, 'edit'])->name('bio.edit');
    Route::patch('bio', [BioPageController::class, 'update'])->name('bio.update');
    Route::get('bio/qr', QrCodeController::class)->name('bio.qr');
    Route::post('bio/domain/verify', [BioPageController::class, 'verifyDomain'])->name('bio.domain.verify');

    Route::get('bio/blocks', [BlockController::class, 'index'])->name('bio.blocks');
    Route::patch('bio/blocks/reorder', [BlockController::class, 'reorder'])->name('bio.blocks.reorder');
    Route::post('bio/blocks', [BlockController::class, 'store'])->name('bio.blocks.store');
    Route::patch('bio/blocks/{block}', [BlockController::class, 'update'])->name('bio.blocks.update');
    Route::delete('bio/blocks/{block}', [BlockController::class, 'destroy'])->name('bio.blocks.destroy');

    Route::post('bio/collaborators', [CollaboratorController::class, 'store'])->name('bio.collaborators.store');
    Route::delete('bio/collaborators/{member}', [CollaboratorController::class, 'destroy'])->name('bio.collaborators.destroy');

    Route::get('restaurant/brand', BrandController::class)->name('restaurant.brand');
    Route::get('restaurant', [RestaurantController::class, 'index'])->name('restaurant.index');
    Route::post('restaurant', [RestaurantController::class, 'store'])->name('restaurant.store');
    Route::patch('restaurant/{profile:username}', [RestaurantController::class, 'update'])->name('restaurant.update');
    Route::get('restaurant/{profile:username}/qr', QrCodeController::class)->name('restaurant.qr');
    Route::get('restaurant/{profile:username}/reviews', [ReviewController::class, 'index'])->name('restaurant.reviews');
    Route::patch('restaurant/{profile:username}/reviews/{review}', [ReviewController::class, 'update'])->name('restaurant.reviews.update');
    Route::delete('restaurant/{profile:username}/reviews/{review}', [ReviewController::class, 'destroy'])->name('restaurant.reviews.destroy');
    Route::get('subscribers/{profile:username}', [SubscriberController::class, 'index'])->name('subscribers.index');
    Route::get('subscribers/{profile:username}/export', [SubscriberController::class, 'export'])->name('subscribers.export');
    Route::post('subscribers/{profile:username}/email', [SubscriberController::class, 'send'])->name('subscribers.send');
    Route::delete('subscribers/{profile:username}/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');

    Route::get('restaurant/{profile:username}/menu', [MenuController::class, 'index'])->name('restaurant.menu');
    Route::patch('restaurant/{profile:username}/menu/settings', [MenuController::class, 'updateSettings'])->name('restaurant.menu.settings');
    Route::post('restaurant/{profile:username}/menu/categories', [MenuController::class, 'storeCategory'])->name('restaurant.menu.categories.store');
    Route::patch('restaurant/{profile:username}/menu/categories/{category}', [MenuController::class, 'updateCategory'])->name('restaurant.menu.categories.update');
    Route::delete('restaurant/{profile:username}/menu/categories/{category}', [MenuController::class, 'destroyCategory'])->name('restaurant.menu.categories.destroy');
    Route::post('restaurant/{profile:username}/menu/categories/{category}/items', [MenuController::class, 'storeItem'])->name('restaurant.menu.items.store');
    Route::patch('restaurant/{profile:username}/menu/items/{item}', [MenuController::class, 'updateItem'])->name('restaurant.menu.items.update');
    Route::delete('restaurant/{profile:username}/menu/items/{item}', [MenuController::class, 'destroyItem'])->name('restaurant.menu.items.destroy');

    Route::post('restaurant/{profile:username}/menu/items/{item}/option-groups', [MenuController::class, 'storeOptionGroup'])->name('restaurant.menu.option-groups.store');
    Route::patch('restaurant/{profile:username}/menu/items/{item}/option-groups/{group}', [MenuController::class, 'updateOptionGroup'])->name('restaurant.menu.option-groups.update');
    Route::delete('restaurant/{profile:username}/menu/items/{item}/option-groups/{group}', [MenuController::class, 'destroyOptionGroup'])->name('restaurant.menu.option-groups.destroy');
    Route::post('restaurant/{profile:username}/menu/items/{item}/option-groups/{group}/options', [MenuController::class, 'storeOption'])->name('restaurant.menu.options.store');
    Route::patch('restaurant/{profile:username}/menu/items/{item}/option-groups/{group}/options/{option}', [MenuController::class, 'updateOption'])->name('restaurant.menu.options.update');
    Route::delete('restaurant/{profile:username}/menu/items/{item}/option-groups/{group}/options/{option}', [MenuController::class, 'destroyOption'])->name('restaurant.menu.options.destroy');

    Route::get('restaurant/{profile:username}/kitchen', KitchenController::class)->name('restaurant.kitchen');

    Route::get('restaurant/{profile:username}/customers', [CustomerController::class, 'index'])->name('restaurant.customers');
    Route::get('restaurant/{profile:username}/customers/export', [CustomerController::class, 'export'])->name('restaurant.customers.export');
    Route::patch('restaurant/{profile:username}/customers/{customer}', [CustomerController::class, 'update'])->name('restaurant.customers.update');

    Route::get('restaurant/{profile:username}/orders', [OrderController::class, 'index'])->name('restaurant.orders');
    Route::patch('restaurant/{profile:username}/orders/{order}', [OrderController::class, 'update'])->name('restaurant.orders.update');

    Route::get('restaurant/{profile:username}/delivery', [DeliveryZoneController::class, 'index'])->name('restaurant.delivery');
    Route::post('restaurant/{profile:username}/delivery', [DeliveryZoneController::class, 'store'])->name('restaurant.delivery.store');
    Route::patch('restaurant/{profile:username}/delivery/{zone}', [DeliveryZoneController::class, 'update'])->name('restaurant.delivery.update');
    Route::delete('restaurant/{profile:username}/delivery/{zone}', [DeliveryZoneController::class, 'destroy'])->name('restaurant.delivery.destroy');

    Route::get('restaurant/{profile:username}/activity', AuditLogController::class)->name('restaurant.activity');

    Route::get('restaurant/{profile:username}/reservations', [ReservationController::class, 'index'])->name('restaurant.reservations');
    Route::patch('restaurant/{profile:username}/reservations/{reservation}', [ReservationController::class, 'update'])->name('restaurant.reservations.update');

    Route::get('restaurant/{profile:username}/reports', [ReportController::class, 'index'])->name('restaurant.reports');
    Route::get('restaurant/{profile:username}/reports/export', [ReportController::class, 'export'])->name('restaurant.reports.export');

    Route::post('restaurant/{profile:username}/promotions', [CouponController::class, 'store'])->name('restaurant.promotions.store');
    Route::patch('restaurant/{profile:username}/promotions/{coupon}', [CouponController::class, 'update'])->name('restaurant.promotions.update');
    Route::delete('restaurant/{profile:username}/promotions/{coupon}', [CouponController::class, 'destroy'])->name('restaurant.promotions.destroy');

    Route::post('restaurant/{profile:username}/team', [TeamController::class, 'store'])->name('restaurant.team.store');
    Route::delete('restaurant/{profile:username}/team/{member}', [TeamController::class, 'destroy'])->name('restaurant.team.destroy');

    Route::patch('restaurant/{profile:username}/requests/{serviceRequest}', [RestaurantController::class, 'completeRequest'])->name('restaurant.requests.complete');

    Route::patch('developers/{profile:username}/pos', [DeveloperConsoleController::class, 'updatePos'])->name('developer.pos.update');
    Route::post('developers/{profile:username}/pos/test', [DeveloperConsoleController::class, 'testPosWebhook'])->name('developer.pos.test');
    Route::post('developers/{profile:username}/pos/tokens', [DeveloperConsoleController::class, 'storePosToken'])->name('developer.pos.tokens.store');
    Route::delete('developers/{profile:username}/pos/tokens/{token}', [DeveloperConsoleController::class, 'destroyPosToken'])->name('developer.pos.tokens.destroy');

    Route::get('menu', function (Request $request) {
        $restaurant = $request->user()->accessibleRestaurants()->first();

        return $restaurant instanceof Profile
            ? redirect()->route('restaurant.menu', $restaurant)
            : redirect()->route('restaurant.index');
    })->name('menu.index');

    Route::get('orders', function (Request $request) {
        $restaurant = $request->user()->accessibleRestaurants()->first();

        return $restaurant instanceof Profile
            ? redirect()->route('restaurant.orders', $restaurant)
            : redirect()->route('restaurant.index');
    })->name('orders.index');

    Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::patch('inbox/{message}', [InboxController::class, 'update'])->name('inbox.update');
    Route::delete('inbox/{message}', [InboxController::class, 'destroy'])->name('inbox.destroy');

    Route::get('links', [LinkController::class, 'index'])->name('links.index');
    Route::post('links', [LinkController::class, 'store'])->name('links.store');
    Route::post('links/{link}/duplicate', [LinkController::class, 'duplicate'])->name('links.duplicate');
    Route::patch('links/reorder', [LinkController::class, 'reorder'])->name('links.reorder');
    Route::patch('links/{link}', [LinkController::class, 'update'])->name('links.update');
    Route::delete('links/{link}', [LinkController::class, 'destroy'])->name('links.destroy');

    Route::post('links/{link}/variants', [LinkVariantController::class, 'store'])->name('links.variants.store');
    Route::patch('links/{link}/variants/{variant}', [LinkVariantController::class, 'update'])->name('links.variants.update');
    Route::delete('links/{link}/variants/{variant}', [LinkVariantController::class, 'destroy'])->name('links.variants.destroy');

    Route::post('socials', [SocialLinkController::class, 'store'])->name('socials.store');
    Route::patch('socials/{social}', [SocialLinkController::class, 'update'])->name('socials.update');
    Route::delete('socials/{social}', [SocialLinkController::class, 'destroy'])->name('socials.destroy');
});

Route::post('webhooks/textbee', TextBeeWebhookController::class)->name('webhooks.textbee');

Route::get('developers', [DeveloperConsoleController::class, 'index'])->name('developer.index');

Route::redirect('developer', 'developers');

Route::get('l/{link}', [LinkController::class, 'redirect'])
    ->name('links.redirect');

Route::get('restaurant/{username}', PublicMenuController::class)
    ->where('username', '[A-Za-z0-9_-]+')
    ->name('restaurant.show');

Route::post('restaurant/{username}/order', [PublicOrderController::class, 'store'])
    ->where('username', '[A-Za-z0-9_-]+')
    ->middleware('throttle:10,1')
    ->name('restaurant.order');

Route::get('restaurant/{username}/order/{reference}', TrackOrderController::class)
    ->where('username', '[A-Za-z0-9_-]+')
    ->where('reference', '[A-Za-z0-9_-]+')
    ->middleware('throttle:30,1')
    ->name('restaurant.track');

Route::get('invitations/{token}', [InvitationController::class, 'accept'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('invitations.accept');

Route::get('unsubscribe/{token}', PublicUnsubscribeController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('subscribers.unsubscribe');

Route::post('subscribe/{username}', [PublicSubscriberController::class, 'store'])
    ->where('username', '[A-Za-z0-9_-]+')
    ->middleware('throttle:10,1')
    ->name('subscribe');

Route::post('restaurant/{username}/review', [PublicReviewController::class, 'store'])
    ->where('username', '[A-Za-z0-9_-]+')
    ->middleware('throttle:6,1')
    ->name('restaurant.review');

Route::post('restaurant/{username}/reservation', [PublicReservationController::class, 'store'])
    ->where('username', '[A-Za-z0-9_-]+')
    ->middleware('throttle:6,1')
    ->name('restaurant.reservation');

Route::post('restaurant/{username}/service-request', [ServiceRequestController::class, 'store'])
    ->where('username', '[A-Za-z0-9_-]+')
    ->middleware('throttle:6,1')
    ->name('restaurant.service-request');

Route::post('{username}/contact', [ProfileContactController::class, 'store'])
    ->where('username', '[A-Za-z0-9_-]+')
    ->middleware('throttle:5,1')
    ->name('bio.contact');

Route::post('{username}/unlock', [BioPageController::class, 'unlock'])
    ->where('username', '[A-Za-z0-9_-]+')
    ->middleware('throttle:6,1')
    ->name('bio.unlock');

Route::get('{username}/vcard', VCardController::class)
    ->where('username', '[A-Za-z0-9_-]+')
    ->name('bio.vcard');

Route::get('{username}', [BioPageController::class, 'show'])
    ->where('username', '[A-Za-z0-9_-]+')
    ->name('bio.show');
