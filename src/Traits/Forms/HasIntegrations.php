<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasIntegrations
{
    public function hasIntegrations(): bool
    {
        return !empty(Integration::getIntegrations());
    }

    /**
     * @return array<Integration>
     */
    public function getIntegrations(FormSubmission $submission): array
    {
        return $submission->getIntegrations();
    }

    public function triggerIntegrations(FormSubmission $submission): void
    {
        if (!$this->hasIntegrations()) {
            return;
        }

        $responses = [];

        foreach ($this->getIntegrations($submission) as $integration) {
            $this->triggerIntegration($integration);

            $responses[] = [
                'integration' => get_class($integration),
                'response' => $integration->responseData(),
            ];
        }

        $submission->update([
            'integrations' => $responses,
        ]);
    }

    public function triggerIntegration(Integration $integration): void
    {
        try {
            $integration->handle();
        } catch (\Exception $e) {
            $integration->setResponse($e->getMessage())
                ->setSuccess(false);
        }
    }
}
