<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasModifiers
{
    /**
     * Modify the form data before validation.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function modifyDataBeforeValidation(array $data): array
    {
        return $data;
    }

    /**
     * Modify the form data before it is processed.
     *
     * @param array $data
     * @return array
     */
    public static function modifyDataUsing(array $data): array
    {
        return $data;
    }

    /**
     * Modify the data shown in the Filament resource detail view.
     *
     * @param array<string, mixed> $data
     * @param FormSubmission $submission
     * @return array<string, mixed>
     */
    public static function modifyResourceDataUsing(array $data, FormSubmission $submission): array
    {
        return $submission->getFormattedData(
            formatKeys: true,
            data: $data
        );
    }
}