<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Mail\PurchaseConfirmed;
use App\Models\Purchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PurchaseController extends Controller
{
    /**
     * Show every purchase and its review status.
     */
    public function index(): Response
    {
        $purchases = Purchase::query()
            ->with(['user:id,name,email', 'plan:id,name,plan_type'])
            ->latest('id')
            ->paginate(20);

        return Inertia::render('admin/purchases/Index', [
            'purchases' => $purchases->through(fn (Purchase $purchase): array => [
                'id' => $purchase->id,
                'customer' => $purchase->user->name,
                'email' => $purchase->user->email,
                'plan_name' => $purchase->plan->name,
                'amount' => $purchase->displayAmount(),
                'status' => $purchase->status->value,
                'transaction_id' => $purchase->paypal_transaction_id,
                'created_at' => $purchase->created_at?->toDateTimeString(),
                'payment_date' => $purchase->payment_date?->toDateTimeString(),
                'expires_at' => $purchase->expires_at?->toDateString(),
                'access' => $purchase->accessLabel(),
            ]),
            'statuses' => PurchaseStatus::values(),
        ]);
    }

    /**
     * Review a purchase and record its confirmation status.
     */
    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(PurchaseStatus::class)],
            'paypal_transaction_id' => ['nullable', 'string', 'max:64'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $wasCompleted = $purchase->status === PurchaseStatus::Completed;

        if (array_key_exists('expires_at', $data) && filled($data['expires_at'])) {
            $data['expires_at'] = Carbon::parse((string) $data['expires_at'])->endOfDay();
        }

        $purchase->fill($data);
        $purchase->loadMissing('plan');

        if ($purchase->status === PurchaseStatus::Completed) {
            if ($purchase->payment_date === null) {
                $purchase->payment_date = Carbon::now();
            }

            if (! $wasCompleted && $purchase->expires_at === null && $purchase->plan->duration_days !== null) {
                $purchase->expires_at = Carbon::now()->addDays($purchase->plan->duration_days);
            }
        }

        $purchase->save();

        if (! $wasCompleted && $purchase->status === PurchaseStatus::Completed) {
            try {
                $purchase->loadMissing(['plan', 'user']);

                Mail::to($purchase->user->email)->send(new PurchaseConfirmed($purchase));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase updated.')]);

        return back();
    }
}
