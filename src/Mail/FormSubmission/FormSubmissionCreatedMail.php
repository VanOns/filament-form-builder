<?php

namespace VanOns\FilamentFormBuilder\Mail\FormSubmission;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionCreatedMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string         $emailSubject,
        public string         $emailContent,
        public FormSubmission $formSubmission,
        public ?string         $sender = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: !empty($this->sender) ? new Address($this->sender) : null,
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'filament-form-builder::mail.form-submission.created',
            with: [
                'subject' => $this->emailSubject,
                'content' => $this->emailContent,
                'formSubmission' => $this->formSubmission,
            ],
        );
    }
}
