<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use Illuminate\Support\Facades\Mail;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Mail\FormSubmission\FormSubmissionCreatedMail;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasNotifications
{
    public static function hasNotifications(): bool
    {
        return true;
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
            Mail::to($receiver)
                ->send(new FormSubmissionCreatedMail(
                    emailSubject: $notification->subject,
                    emailContent: $notification->content,
                    formSubmission: $submission,
                ));
        }
    }
}
