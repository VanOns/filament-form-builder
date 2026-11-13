<?php

namespace VanOns\FilamentFormBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;
use VanOns\FilamentFormBuilder\Enums\NotificationStatus;
use VanOns\FilamentFormBuilder\Mail\FormSubmission\FormSubmissionCreatedMail;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

class SendFormNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $notificationLogId,
        public FormSubmissionCreatedMail $mail,
        public string $receiver,
        /**
         * @var list<string>
         */
        public array $cc = [],
        /**
         * @var list<string>
         */
        public array $bcc = [],
    ) {
    }

    public function handle(): void
    {
        Mail::to($this->receiver)
            ->cc($this->cc)
            ->bcc($this->bcc)
            ->send($this->mail);

        FormSubmissionNotificationLog::find($this->notificationLogId)?->update([
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        FormSubmissionNotificationLog::find($this->notificationLogId)?->update([
            'status' => NotificationStatus::Failed,
            'error' => $e->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
