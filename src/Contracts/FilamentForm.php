<?php

namespace VanOns\FilamentFormBuilder\Contracts;

use Illuminate\Support\HtmlString;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

interface FilamentForm extends HasNotifications
{
    public function hasRecaptcha(): bool;

    /**
     * @return string[]
     */
    public function getDefaultPlaceholders(): array;

    public static function isCustom(): bool;

    /**
     * @return array<string, string>
     */
    public function rules(): array;

    /**
     * @return array<string, string>
     */
    public function getRules(): array;

    /**
     * Set fallback values for empty fields & used to inform the user about available placeholders.
     *
     * @return array<int|string, string>
     */
    public function placeholders(): array;

    /**
     * @return array<string, string>
     */
    public function getPlaceholders(): array;

    /**
     * Returns an HTML string with the placeholders formatted for display.
     *
     * @return HtmlString
     */
    public function getPlaceholdersHtmlString(): HtmlString;

    /**
     * @return array<string, string>
     */
    public function attributes(): array;

    public function findAttributeForKey(string $key): string;

    /**
     * @return array<string, string>
     */
    public function messages(): array;

    /**
     * Fully modify the response after a successful form submission.
     *
     * @param FormSubmission $submission
     * @return mixed
     */
    public static function successResponse(FormSubmission $submission): mixed;

    /**
     * Modify the form data before validation.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function modifyDataBeforeValidation(array $data): array;

    /**
     * Modify the form data before it is processed.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function modifyDataUsing(array $data): array;

    /**
     * Hook to perform actions after a form submission is created.
     *
     * @param FormSubmission $submission
     * @return void
     */
    public static function afterSubmissionCreated(FormSubmission $submission): void;

    /**
     * Modify the data shown in the Filament resource detail view.
     *
     * @param array<string, mixed> $data
     * @param FormSubmission $submission
     * @return array<string, mixed>
     */
    public static function modifyResourceDataUsing(array $data, FormSubmission $submission): array;
}
