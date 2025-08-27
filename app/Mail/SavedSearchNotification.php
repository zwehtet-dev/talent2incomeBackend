<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SavedSearchNotification extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SavedSearch $savedSearch,
        public Collection $newResults
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $count = $this->newResults->count();
        $type = $this->savedSearch->type;

        return new Envelope(
            subject: "New {$type} found for your saved search: {$this->savedSearch->name} ({$count} new)",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.saved-search-notification',
            with: [
                'savedSearch' => $this->savedSearch,
                'newResults' => $this->newResults,
                'searchUrl' => $this->savedSearch->getSearchUrl(),
                'unsubscribeUrl' => route('saved-searches.unsubscribe', $this->savedSearch->id),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
