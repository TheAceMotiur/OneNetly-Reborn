<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Order $order) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Order :reference received', ['reference' => $this->order->reference]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'customerName' => $this->order->customer_name,
                'profileName' => $this->order->profile->restaurantName(),
                'reference' => $this->order->reference,
                'orderType' => $this->order->typeLabel(),
                'tableNumber' => $this->order->table_number,
                'items' => $this->order->items,
                'currency' => $this->order->currency,
                'total' => $this->order->displayTotal(),
                'trackUrl' => route('restaurant.track', [
                    $this->order->profile->username,
                    $this->order->reference,
                ]),
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
