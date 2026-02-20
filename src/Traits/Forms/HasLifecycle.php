<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasLifecycle
{
    /**
     * Hook to perform actions after a form submission is created.
     *
     * @param FormSubmission $submission
     * @return void
     */
    public static function afterSubmissionCreated(FormSubmission $submission): void
    {
        self::triggerNotifications($submission);
    }
}