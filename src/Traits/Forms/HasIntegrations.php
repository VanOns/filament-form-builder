<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasIntegrations
{
    public static function hasIntegrations(): bool
    {
        return true;
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
            $integration->handle();
        }
    }
}