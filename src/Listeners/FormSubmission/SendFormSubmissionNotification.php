<?php

namespace VanOns\FilamentFormBuilder\Listeners\FormSubmission;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionCreated;
use VanOns\FilamentFormBuilder\Mail\FormSubmission\FormSubmissionCreatedMail;

class SendFormSubmissionNotification implements ShouldQueue
{
    public function handle(FormSubmissionCreated $formSubmissionCreated): void
    {
        $notifications = $formSubmissionCreated->formSubmission->getNotifications();

        foreach ($notifications as $notification) {
            foreach ($notification->receivers as $receiver) {
                Mail::to($receiver)
                    ->send(new FormSubmissionCreatedMail(
                        emailSubject: $notification->subject,
                        emailContent: $notification->content,
                        formSubmission: $formSubmissionCreated->formSubmission,
                    ));
            }
        }
    }
}
