<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasIntegrations
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected static array $integrationResponses = [];

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
        if (!self::hasIntegrations()) {
            return;
        }

        foreach ($submission->getIntegrations() as $integration) {
            self::triggerIntegration($integration);
        }

        self::saveIntegrationResponses($submission);
    }

    public static function triggerIntegration(Integration $integration): void
    {
        try {
            $integration->handle();
        } catch (\Exception $e) {
            $integration->setResponse($e->getMessage())
                ->setSuccess(false);
        }

        self::storeIntegrationResponse($integration);
    }

    protected static function storeIntegrationResponse(Integration $integration): void
    {
        self::$integrationResponses[] = [
            'integration' => get_class($integration),
            'response' => $integration->responseData(),
        ];
    }

    protected static function saveIntegrationResponses(FormSubmission $submission): void
    {
        $submission->update([
            'integrations' => self::$integrationResponses,
        ]);
    }
}
