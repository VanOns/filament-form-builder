<?php

namespace VanOns\FilamentFormBuilder\Events\FormSubmission;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionForceDeleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public FormSubmission $formSubmission)
    {
    }
}
