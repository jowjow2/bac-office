<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        return new Content(view: 'emails.bidder-verify-email', text: 'emails.bidder-verify-email-text');
    }

    public function send($mailer)
    {
        $name = $this->mailer ?: config('mail.default');
        $transport = config("mail.mailers.{$name}.transport");
        $localOnly = in_array($transport, ['log', 'array'], true);
        $logsOnFailure = $transport === 'failover' && collect(config("mail.mailers.{$name}.mailers", []))
            ->contains(fn ($child) => in_array(config("mail.mailers.{$child}.transport"), ['log', 'array'], true));

        // A log fallback must never count as delivery of an account-confirmation link.
        if (app()->environment('production') && ($localOnly || $logsOnFailure)) {
            if (! config('mail.mailers.smtp.url') && (! config('mail.mailers.smtp.username') || ! config('mail.mailers.smtp.host'))) {
                throw new \RuntimeException('Bidder confirmation requires a configured email delivery transport.');
            }
            $name = 'smtp';
            $transport = 'smtp';
            $mailer = Mail::mailer($name);
            $this->mailer($name);
        }

        $context = ['user_id' => $this->user->id, 'mailer' => $name, 'transport' => $transport];
        Log::info('bidder_confirmation_sending', $context);
        $sent = parent::send($mailer);
        if ($sent === null) {
            throw new \RuntimeException('The confirmation email was not accepted for sending.');
        }
        // Keep addresses, signed confirmation links and mail credentials out of these logs.
        Log::info('bidder_confirmation_accepted', $context + [
            'message_id_hash' => hash('sha256', $sent->getMessageId()),
        ]);

        return $sent;
    }
}
