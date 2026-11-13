<?php

namespace VanOns\FilamentFormBuilder\Mail\FormSubmission;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionCreatedMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  list<array{path: string, name: string}>  $files  uploads on the form's disk
     */
    public function __construct(
        public string $emailSubject,
        public string $emailContent,
        public FormSubmission $formSubmission,
        public ?string $sender = null,
        public ?string $senderName = null,
        public ?string $replyToAddress = null,
        public array $files = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: !empty($this->sender) ? new Address($this->sender, $this->senderName ?: null) : null,
            replyTo: $this->replyToAddress !== null ? [new Address($this->replyToAddress)] : [],
            subject: $this->emailSubject,
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $file): Attachment => Attachment::fromStorageDisk(FormSubmission::getFilesDisk(), $file['path'])->as($file['name']),
            $this->files,
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
