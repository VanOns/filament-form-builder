<?php

namespace VanOns\FilamentFormBuilder\Contracts;

use Filament\Schemas\Components\Component;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

interface FilamentForm extends HasNotifications, HasIntegrations
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
     * Returns the available placeholders formatted for display.
     *
     * @return array<int, string>
     */
    public function getPlaceholderList(): array;

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
    public static function modifyDataBeforeValidation(array $data, Form $form): array;

    /**
     * Modify the form data before it is processed.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function modifyDataUsing(array $data, Form $form): array;

    /**
     * Hook to perform actions after a form submission is created.
     *
     * @param FormSubmission $submission
     * @return void
     */
    public static function afterSubmissionCreated(FormSubmission $submission): void;

    /**
     * Modify the submission values while keeping the original field-name keys.
     * Applied to both the resource detail view and notification emails.
     *
     * @param array<string, mixed> $data
     * @param FormSubmission $submission
     * @return array<string, mixed>
     */
    public static function modifyDataValues(array $data, FormSubmission $submission): array;

    /**
     * Modify the data shown in the Filament resource detail view.
     *
     * @param array<string, mixed> $data
     * @param FormSubmission $submission
     * @return array<string, mixed>
     */
    public static function modifyResourceDataUsing(array $data, FormSubmission $submission): array;

    /**
     * Whether the redirect URL is configurable in the admin.
     */
    public static function hasRedirect(): bool;

    /**
     * Whether the notification message is configurable in the admin.
     */
    public static function hasNotificationMessage(): bool;

    /**
     * Modify the redirect URL and/or notification message after a submission.
     *
     * @param FormSubmission $submission
     * @return void
     */
    public function modifySubmitNotification(FormSubmission $submission): void;

    /**
     * Fill the redirect URL and notification message, then run the template hook.
     *
     * @param FormSubmission $submission
     * @return static
     */
    public function resolveSubmitNotification(FormSubmission $submission): static;

    public function getRedirectUrl(): ?string;

    public function getNotificationMessage(): ?string;

    public function getNotificationType(): ?string;

    /**
     * Return additional Filament form components rendered in the admin settings section.
     * Values are stored in the `settings` JSON column on the Form model.
     *
     * @return array<Component>
     */
    public static function settings(): array;
}
