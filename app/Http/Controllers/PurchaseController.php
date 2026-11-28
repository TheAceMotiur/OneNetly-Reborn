<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PurchaseController extends Controller
{
    /**
     * Show the purchase history of the signed-in user.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $purchases = $user->purchases()
            ->with('plan')
            ->latest('id')
            ->get();

        return Inertia::render('purchases/Index', [
            'purchases' => $purchases->map(fn (Purchase $purchase): array => [
                'id' => $purchase->id,
                'plan_name' => $purchase->plan->name,
                'plan_type' => $purchase->plan->typeDisplayName(),
                'amount' => $purchase->displayAmount(),
                'status' => $purchase->statusDisplay(),
                'status_class' => $purchase->statusClass(),
                'transaction_id' => $purchase->paypal_transaction_id,
                'payment_date' => $purchase->payment_date?->toDateString(),
                'access' => $purchase->accessLabel(),
                'created_at' => $purchase->created_at?->toDateString(),
            ])->all(),
        ]);
    }

    /**
     * Start a purchase by sending the buyer to the owner's PayPal.me page.
     */
    public function store(Request $request): RedirectResponse|SymfonyResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
        ]);

        /** @var Plan $plan */
        $plan = Plan::query()->findOrFail($data['plan_id']);

        /** @var User $user */
        $user = $request->user();

        $alreadyOwned = $plan->isToolPlan()
            ? $user->hasToolAccess((string) $plan->tool)
            : $plan->isBundlePlan() && $user->activePlans()->contains(fn (Plan $active): bool => $active->isBundlePlan());

        if ($alreadyOwned) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('You already have access to :plan.', ['plan' => $plan->name]),
            ]);

            return to_route('pricing.index');
        }

        if ((float) $plan->price <= 0) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('The :plan plan is free and already available to everyone.', ['plan' => $plan->name]),
            ]);

            return to_route('pricing.index');
        }

        if (! setting('payments_enabled')) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Payments are temporarily disabled. Please try again later.'),
            ]);

            return to_route('pricing.index');
        }

        $paypalUsername = setting('paypal_me_username') ?: config('services.paypal.me_username');

        if (blank($paypalUsername)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Payments are not configured yet. Please try again later.'),
            ]);

            return to_route('pricing.index');
        }

        $purchase = $user->purchases()->create([
            'plan_id' => $plan->id,
            'status' => PurchaseStatus::Pending,
            'amount' => (float) $plan->price,
            'currency' => $plan->currency,
            'payer_email' => $user->email,
        ]);

        $amount = number_format((float) $plan->price, 2, '.', '');

        return Inertia::location(sprintf(
            'https://paypal.me/%s/%s%s',
            rawurlencode((string) $paypalUsername),
            $amount,
            $plan->currency,
        ));
    }

    /**
     * Show the confirmation screen after the buyer returns from PayPal.
     */
    public function getReturn(Request $request, Purchase $purchase): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $purchase->user_id === $user->id || $user->is_admin,
            403,
        );

        return Inertia::render('purchases/Return', [
            'purchase' => [
                'id' => $purchase->id,
                'plan_name' => $purchase->plan->name,
                'amount' => $purchase->displayAmount(),
                'status' => $purchase->statusDisplay(),
            ],
        ]);
    }

    /**
     * Send the buyer back to pricing after cancelling on PayPal.
     */
    public function getCancel(): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('Payment cancelled. You have not been charged.'),
        ]);

        return to_route('pricing.index');
    }
}
