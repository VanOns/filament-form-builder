<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasIntegrations
{
    public static function hasIntegrations(): bool
    {
        return !empty(Integration::getIntegrations());
    }

    public static function getIntegrations(FormSubmission $submission): array
    {
        return $submission->getIntegrations();
    }

    public static function triggerIntegrations(FormSubmission $submission): void
    {
        if (!static::hasIntegrations()) {
            return;
        }

        $responses = [];

        foreach (static::getIntegrations($submission) as $integration) {
            static::triggerIntegration($integration);

            $responses[] = [
                'integration' => get_class($integration),
                'response' => $integration->responseData(),
            ];
        }

        $submission->update([
            'integrations' => $responses,
        ]);
    }

    public static function triggerIntegration(Integration $integration): void
    {
        try {
            $integration->handle();
        } catch (\Exception $e) {
            $integration->setResponse($e->getMessage())
                ->setSuccess(false);
        }
    }
}
