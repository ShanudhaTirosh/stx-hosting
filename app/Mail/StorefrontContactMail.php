<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A message from the public contact form, delivered to staff with the sender as reply-to. */
class StorefrontContactMail extends Mailable
{
    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $department,
        public ?string $subjectLine,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: '[Contact: '.$this->department.'] '.($this->subjectLine ?: 'New message from '.$this->senderName),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.storefront-contact-text');
    }
}
