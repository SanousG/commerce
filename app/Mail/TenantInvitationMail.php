<?php

namespace App\Mail;

use App\Modules\Tenancy\Domain\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly TenantInvitation $invitation,
        private readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invitation to '.$this->invitation->tenant->name);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.tenant-invitation',
            with: ['acceptUrl' => route('invitations.show', ['token' => $this->token])],
        );
    }
}
