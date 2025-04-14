<?php

namespace VanOns\FilamentFormBuilder\Mail\FormSubmission;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionCreated extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public FormSubmission $formSubmission)
    {
    }

    public function envelope(): Envelope
    {
        $formTitle = $this->formSubmission->form->title;

        return new Envelope(
            subject: "Form {$formTitle} submitted",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'filament-form-builder::mail.form-submission.created',
            with: [
                'formSubmission' => $this->formSubmission,
                'form' => $this->formSubmission->form,
            ],
        );
    }
}
