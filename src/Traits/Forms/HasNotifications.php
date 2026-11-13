<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use Throwable;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Enums\NotificationStatus;
use VanOns\FilamentFormBuilder\Jobs\SendFormNotificationJob;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

trait HasNotifications
{
    public function hasNotifications(): bool
    {
        return config('filament-form-builder.email_notifications') === true;
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

        $mail = $notification->toMail();

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
                    'status' => NotificationStatus::Queued,
                ]);

                SendFormNotificationJob::dispatch(
                    notificationLogId: $log->id,
                    mail: $mail,
                    receiver: $receiver,
                    cc: $cc,
                    bcc: $bcc,
                );
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
