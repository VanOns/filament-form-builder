<?php

namespace VanOns\FilamentFormBuilder\Mail\FormSubmission;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $emailContent,
        public FormSubmission $formSubmission,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
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
