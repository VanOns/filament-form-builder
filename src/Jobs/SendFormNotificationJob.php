<?php

namespace VanOns\FilamentFormBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;
use VanOns\FilamentFormBuilder\Mail\FormSubmission\FormSubmissionCreatedMail;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

class SendFormNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $notificationLogId,
        public string $subject,
        public string $content,
        public ?string $sender,
        public string $receiver,
        public FormSubmission $formSubmission,
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->receiver)
            ->send(new FormSubmissionCreatedMail(
                emailSubject: $this->subject,
                emailContent: $this->content,
                formSubmission: $this->formSubmission,
                sender: $this->sender,
            ));

        FormSubmissionNotificationLog::find($this->notificationLogId)?->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        FormSubmissionNotificationLog::find($this->notificationLogId)?->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
