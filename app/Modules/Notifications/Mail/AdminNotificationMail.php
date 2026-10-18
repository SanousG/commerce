<?php

namespace App\Modules\Notifications\Mail;

use App\Modules\Notifications\Domain\Models\AdminNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly AdminNotification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Admin] '.$this->notification->title);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-notification',
            with: [
                'title' => $this->notification->title,
                'notificationMessage' => $this->notification->message,
                'actionUrl' => $this->notification->action_url === null
                    ? null
                    : url($this->notification->action_url),
            ],
        );
    }
}
