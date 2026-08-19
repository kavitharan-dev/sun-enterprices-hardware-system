<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ImportantAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $alertTitle,
        public string $alertBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('brand.name').' — '.$this->alertTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.important-alert',
            with: [
                'alertTitle' => $this->alertTitle,
                'alertBody' => $this->alertBody,
            ],
        );
    }
}
