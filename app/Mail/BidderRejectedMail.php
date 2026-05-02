<?php

namespace App\Mail;

use App\Models\Bidder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BidderRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Bidder $bidder,
        public ?string $reason = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'BAC Office Registration Update',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bidder-rejected',
        );
    }
}
