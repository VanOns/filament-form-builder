<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Jobs\SendFormNotificationJob;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

trait HasNotifications
{
    public static function hasNotifications(): bool
    {
        return config('filament-form-builder.email_notification_enabled') === true;
    }

    public static function getNotifications(FormSubmission $submission): array
    {
        return $submission->getNotifications();
    }

    public static function triggerNotifications(FormSubmission $submission): void
    {
        if (!self::hasNotifications()) {
            return;
        }

        self::sendNotifications($submission);
    }

    public static function sendNotifications(FormSubmission $submission): void
    {
        foreach (self::getNotifications($submission) as $notification) {
            self::sendNotification($notification, $submission);
        }
    }

    public static function sendNotification(EmailNotification $notification, FormSubmission $submission): void
    {
        foreach ($notification->receivers as $receiver) {
            try {
                $log = FormSubmissionNotificationLog::create([
                    'form_submission_id' => $submission->id,
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
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
