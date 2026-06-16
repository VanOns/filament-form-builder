<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasModifiers
{
    /**
     * Modify the form data before validation.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function modifyDataBeforeValidation(array $data, Form $form): array
    {
        return $data;
    }

    /**
     * Modify the form data before it is processed.
     *
     * @param array $data
     * @param Form $form
     * @return array
     */
    public static function modifyDataUsing(array $data, Form $form): array
    {
        return $data;
    }

    /**
     * Modify the submission values while keeping the original field-name keys.
     *
     * Use this to turn stored values into human-readable output (e.g. mapping an
     * enum value to its label). It is applied to both the Filament resource detail
     * view and the notification emails, so formatting stays consistent across both.
     *
     * @param array<string, mixed> $data
     * @param FormSubmission $submission
     * @return array<string, mixed>
     */
    public static function modifyDataValues(array $data, FormSubmission $submission): array
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
            data: static::modifyDataValues($data, $submission),
        );
    }
}
