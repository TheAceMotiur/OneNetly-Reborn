<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOrderReceived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Order $order)
    {
        $this->order->loadMissing(['items', 'profile']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New order :reference from :name', [
                'reference' => $this->order->reference,
                'name' => $this->order->customer_name,
            ]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new-order',
            with: [
                'reference' => $this->order->reference,
                'customerName' => $this->order->customer_name,
                'customerPhone' => $this->order->customer_phone,
                'customerEmail' => $this->order->customer_email,
                'orderType' => $this->order->typeLabel(),
                'tableNumber' => $this->order->table_number,
                'notes' => $this->order->notes,
                'items' => $this->order->items,
                'total' => $this->order->displayTotal(),
                'currency' => $this->order->currency,
                'profileName' => $this->order->profile->restaurantName(),
                'ordersUrl' => route('restaurant.orders', $this->order->profile),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
