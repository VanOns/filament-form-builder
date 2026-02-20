<?php

namespace VanOns\FilamentFormBuilder\Contracts;

use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

interface HasIntegrations
{
    public static function hasIntegrations(): bool;

    /**
     * @param FormSubmission $submission
     * @return array<Integration>
     */
    public static function getIntegrations(FormSubmission $submission): array;

    public static function triggerIntegrations(FormSubmission $submission): void;
}