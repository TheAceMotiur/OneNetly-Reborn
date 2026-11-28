<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserAdminRequest;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Show the list of users.
     */
    public function index(): Response
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at'])
            ->latest()
            ->paginate(15);

        return Inertia::render('admin/users/Index', [
            'users' => $users->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'plan_id' => $user->currentPlan()?->id,
                'plan_name' => $user->currentPlan()?->name,
            ]),
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->get(['id', 'name', 'plan_type']),
        ]);
    }

    /**
     * Grant or revoke admin access for the given user.
     */
    public function update(UpdateUserAdminRequest $request, User $user): RedirectResponse
    {
        $isAdmin = $request->boolean('is_admin');

        abort_if(
            $user->is_admin && ! $isAdmin && User::where('is_admin', true)->count() === 1,
            403,
            __('At least one administrator is required.'),
        );

        $user->is_admin = $isAdmin;
        $user->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isAdmin
                ? __(':name is now an administrator.', ['name' => $user->name])
                : __(':name is no longer an administrator.', ['name' => $user->name]),
        ]);

        return back();
    }

    /**
     * Assign a plan to a user without a PayPal payment.
     */
    public function assignPlan(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
        ]);

        /** @var Plan $plan */
        $plan = Plan::query()->findOrFail($data['plan_id']);

        if ($user->currentPlan()?->id === $plan->id) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __(':name already has the :plan plan.', ['name' => $user->name, 'plan' => $plan->name]),
            ]);

            return back();
        }

        $user->purchases()->create([
            'plan_id' => $plan->id,
            'status' => PurchaseStatus::Completed,
            'amount' => (float) $plan->price,
            'currency' => $plan->currency,
            'payer_email' => $user->email,
            'payment_date' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('The :plan plan was assigned to :name.', ['plan' => $plan->name, 'name' => $user->name]),
        ]);

        return back();
    }
}
