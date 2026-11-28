<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactController extends Controller
{
    /**
     * Add a contact manually.
     */
    public function store(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120'],
            'opted_in' => ['sometimes', 'boolean'],
        ]);

        $phone = WhatsAppContact::normalisePhone($data['phone']);

        if ($phone === '') {
            return back()->withErrors(['phone' => __('Enter a valid phone number with country code.')]);
        }

        $profile->whatsappContacts()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'source' => 'manual',
                'opted_in' => (bool) ($data['opted_in'] ?? true),
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact saved.')]);

        return back();
    }

    /**
     * Update a contact's details or opt-in state.
     */
    public function update(Request $request, WhatsAppContact $contact): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($contact->profile_id === $profile->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'opted_in' => ['sometimes', 'boolean'],
        ]);

        $contact->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return back();
    }

    /**
     * Remove a contact.
     */
    public function destroy(Request $request, WhatsAppContact $contact): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($contact->profile_id === $profile->id, 404);

        $contact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact removed.')]);

        return back();
    }

    /**
     * Copy phone numbers from orders and reservations into the contact list.
     */
    public function sync(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $added = 0;

        $phones = collect();

        $profile->orders()
            ->select(['customer_phone', 'customer_name'])
            ->get()
            ->each(function ($order) use ($phones): void {
                $phones->push([
                    'phone' => $order->customer_phone,
                    'name' => $order->customer_name,
                    'source' => 'order',
                ]);
            });

        $profile->reservations()
            ->select(['phone', 'name'])
            ->get()
            ->each(function ($reservation) use ($phones): void {
                $phones->push([
                    'phone' => $reservation->phone,
                    'name' => $reservation->name,
                    'source' => 'reservation',
                ]);
            });

        foreach ($phones as $entry) {
            $phone = WhatsAppContact::normalisePhone($entry['phone']);

            if ($phone === '') {
                continue;
            }

            $contact = $profile->whatsappContacts()->firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => $entry['name'],
                    'source' => $entry['source'],
                    'opted_in' => true,
                ],
            );

            if ($contact->wasRecentlyCreated) {
                $added++;
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count new contact(s) imported from orders and bookings.', ['count' => $added]),
        ]);

        return back();
    }
}
