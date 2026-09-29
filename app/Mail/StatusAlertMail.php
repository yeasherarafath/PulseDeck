<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StatusAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public string $subjectLine,
        public array $lines = [],
        public ?string $actionUrl = null,
        public string $eventLabel = '',
        public ?string $unsubscribeUrl = null,
    ) {
        //
    }

    public function envelope(): object
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): object
    {
        return new Content(markdown: 'emails.status-alert');
    }
}
