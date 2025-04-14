<?php

namespace VanOns\FilamentFormBuilder\Listeners\FormSubmission;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionCreated;
use VanOns\FilamentFormBuilder\Mail\FormSubmission\FormSubmissionCreated as FormSubmissionMailable;
use VanOns\FilamentFormBuilder\Models\Form;

class SendFormSubmissionNotification implements ShouldQueue
{
    public function handle(FormSubmissionCreated $formSubmissionCreated): void
    {
        /** @var Form $form */
        $form = $formSubmissionCreated->formSubmission->form;

        if (
            $form->notification_enabled &&
            config('filament-form-builder.email_notification_enabled', true)
        ) {
            Mail::to($form->notification_receivers)
                ->send(new FormSubmissionMailable(
                    $formSubmissionCreated->formSubmission
                ));
        }
    }
}
