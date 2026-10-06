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
     * @param array<string, mixed> $data
     * @param Form $form
     * @return array<string, mixed>
     */
    public static function modifyDataUsing(array $data, Form $form): array
    {
        return $data;
    }

    /**
     * Modify the submission values while keeping the original field-name keys.
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
     * Modify the answers shown on the detail page, already as text under their labels.
     *
     * @param array<string, string> $data
     * @param FormSubmission $submission
     * @return array<string, string>
     */
    public static function modifyResourceDataUsing(array $data, FormSubmission $submission): array
    {
        return $data;
    }
}
