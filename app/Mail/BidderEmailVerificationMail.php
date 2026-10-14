<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class BidderEmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $link;

    public function __construct(public User $user)
    {
        $this->link = URL::temporarySignedRoute('bidder.email.verify', now()->addDays(3), [
            'user' => $user->id,
            'hash' => sha1((string) $user->email),
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your email address - SJBAC bidder registration');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bidder-verify-email');
    }
}
