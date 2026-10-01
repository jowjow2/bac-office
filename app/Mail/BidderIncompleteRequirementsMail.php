<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BidderIncompleteRequirementsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $bidderName,
        public string $companyName,
        public array $missingRequirements,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SJBAC Registration Requirements Incomplete',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bidder-incomplete-requirements',
        );
    }
}
