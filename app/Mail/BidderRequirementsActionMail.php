<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BidderRequirementsActionMail extends Mailable
{
    use Queueable, SerializesModels;

    public Carbon $requestedAt;

    public function __construct(
        public string $bidderName,
        public string $companyName,
        public array $requirements,
        public string $reason,
        ?Carbon $requestedAt = null,
        public ?string $adminReplyToEmail = null,
        public ?string $resubmissionUrl = null,
    ) {
        $this->requestedAt = $requestedAt ?? now();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action needed: SJBAC Portal registration requirements',
            replyTo: $this->adminReplyToEmail ? [new Address($this->adminReplyToEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bidder-requirements-action',
            with: [
                'resubmissionUrl' => $this->resubmissionUrl ?? route('bidder.company-profile'),
            ],
        );
    }
}
