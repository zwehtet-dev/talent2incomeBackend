<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QueueHealthAlert extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param array<array<string, mixed>> $alerts
     */
    public function __construct(
        public array $alerts,
        public string $subject = 'Queue Health Alert',
        public string $severity = 'warning'
    ) {
    }

    public function build()
    {
        return $this->subject($this->subject)
            ->view('emails.queue-health-alert')
            ->with([
                'alerts' => $this->alerts,
                'severity' => $this->severity,
            ]);
    }
}
