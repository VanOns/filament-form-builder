<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use Throwable;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Classes\SubmissionFile;
use VanOns\FilamentFormBuilder\Jobs\SendFormNotificationJob;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

trait HasNotifications
{
    public function hasNotifications(): bool
    {
        return config('filament-form-builder.email_notification_enabled') === true;
    }

    /**
     * @return array<EmailNotification>
     */
    public function getNotifications(FormSubmission $submission): array
    {
        return $submission->getNotifications();
    }

    public function triggerNotifications(FormSubmission $submission): void
    {
        if (!$this->hasNotifications()) {
            return;
        }

        $this->sendNotifications($submission);
    }

    public function sendNotifications(FormSubmission $submission): void
    {
        foreach ($this->getNotifications($submission) as $notification) {
            $this->sendNotification($notification, $submission);
        }
    }

    public function sendNotification(EmailNotification $notification, FormSubmission $submission): void
    {
        if (!$notification->shouldSend()) {
            return;
        }

        $attachments = array_map(
            fn (SubmissionFile $file): array => ['path' => $file->path, 'name' => $file->name],
            $notification->attachments,
        );

        foreach ($notification->receivers as $receiver) {
            // Every recipient gets a mail of their own; the copies go along with each, but not to whoever it is already for.
            $cc = array_values(array_diff($notification->cc, [$receiver]));
            $bcc = array_values(array_diff($notification->bcc, [$receiver, ...$cc]));

            try {
                $log = FormSubmissionNotificationLog::create([
                    'form_submission_id' => $submission->id,
                    'notification_id' => $notification->id,
                    'notification_subject' => $notification->subject,
                    'sender' => $notification->sender,
                    'recipient' => $receiver,
                    'status' => 'queued',
                ]);

                SendFormNotificationJob::dispatch(
                    notificationLogId: $log->id,
                    subject: $notification->subject,
                    content: $notification->content,
                    sender: $notification->sender,
                    receiver: $receiver,
                    formSubmission: $submission,
                    senderName: $notification->senderName,
                    replyTo: $notification->replyTo,
                    attachments: $attachments,
                    cc: $cc,
                    bcc: $bcc,
                );
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
