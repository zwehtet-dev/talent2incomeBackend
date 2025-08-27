<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public array $data = [],
        public ?string $customSubject = null
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customSubject ?? 'Payment Received - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-received',
            with: [
                'user' => $this->user,
                'payment' => $this->data['payment'] ?? null,
                'job' => $this->data['job'] ?? null,
                'amount' => $this->data['amount'] ?? 0,
            ],
        );
    }
}
