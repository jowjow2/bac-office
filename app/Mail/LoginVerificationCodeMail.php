<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
        public int $minutes = 10
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SJBAC Login Verification Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-verification-code',
        );
    }
}
