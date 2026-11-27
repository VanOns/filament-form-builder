<?php

namespace VanOns\FilamentFormBuilder\Events\FormSubmission;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

/**
 * A visitor sent a form and it was stored, after the form type's
 * afterSubmission(). FormSubmissionCreated also fires for a submission a
 * seeder or import creates; this one does not.
 */
class FormSubmitted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public FormSubmission $formSubmission)
    {
    }
}
