<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BidDocumentReviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $projectTitle,
        public string $documentLabel,
        public string $status,
        public int $version,
        public ?string $comment = null,
    ) {}

    public function build(): static
    {
        $subject = $this->status === 'accepted'
            ? 'Bid document accepted: '.$this->projectTitle
            : 'Action required: revise a bid document';

        return $this->subject($subject)->view('emails.bid-document-review')->with([
            'myBidsUrl' => route('bidder.my-bids'),
        ]);
    }
}