<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasResponses
{
    /**
     * Fully modify the response after a successful form submission.
     *
     * @param FormSubmission $submission
     * @return mixed
     */
    public static function successResponse(FormSubmission $submission): mixed
    {
        return null;
    }
}