<?php

namespace App\Mail;

use App\Models\Bidder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BidderApprovedQrMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Bidder $bidder,
        public string $qrSvg,
        public string $qrDataUri,
        public string $loginUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'BAC Office Registration Approved - QR Login Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bidder-approved-qr',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->qrSvg, 'bac-office-qr-login.svg')
                ->withMime('image/svg+xml'),
        ];
    }
}
