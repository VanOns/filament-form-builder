<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Jobs\RunFormIntegrationsJob;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasLifecycle
{
    /**
     * Runs for a visitor's submission, not for one a seeder or import creates.
     * Integrations call other systems, so they wait in the queue.
     */
    public static function afterSubmissionCreated(FormSubmission $submission): void
    {
        static::triggerNotifications($submission);

        if (static::hasIntegrations() && filled($submission->form?->integrations)) {
            RunFormIntegrationsJob::dispatch($submission);
        }
    }
}
