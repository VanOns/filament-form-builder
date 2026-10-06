<?php

namespace VanOns\FilamentFormBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class RunFormIntegrationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public FormSubmission $formSubmission,
    ) {
    }

    public function handle(): void
    {
        if ($template = TemplateHelper::resolve($this->formSubmission->form?->template)) {
            $template::triggerIntegrations($this->formSubmission);
        }
    }
}
