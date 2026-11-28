<?php

namespace App\Mail;

use App\Models\ProfileInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public ProfileInvitation $invitation,
        public User $inviter,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':name invited you to :page', [
                'name' => $this->inviter->name,
                'page' => $this->invitation->profile->restaurantName(),
            ]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.profile-invitation',
            with: [
                'inviterName' => $this->inviter->name,
                'pageName' => $this->invitation->profile->restaurantName(),
                'role' => ucfirst($this->invitation->role),
                'acceptUrl' => route('invitations.accept', $this->invitation->token),
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
