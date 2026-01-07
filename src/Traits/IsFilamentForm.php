<?php

namespace VanOns\FilamentFormBuilder\Traits;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Services\RecaptchaService;

trait IsFilamentForm
{
    public function __construct(public Form $form)
    {
    }

    public function hasRecaptcha(): bool
    {
        return $this->recaptcha ?? false;
    }

    /**
     * @returns array<string>
     */
    public function getDefaultPlaceholders(): array
    {
        return $this->defaultPlaceholders ?? [
            'all_fields',
            'form_title',
        ];
    }

    /**
     * Determines if the form component has a custom form builder.
     *
     * @return bool
     */
    public static function isCustom(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function getRules(): array
    {
        $recaptchaRule = $this->hasRecaptcha() && RecaptchaService::checkEnabled()
            ? RecaptchaService::getRules()
            : [];

        return [
            ...$recaptchaRule,
            ...$this->rules(),
        ];
    }

    /**
     * Set fallback values for empty fields & used to inform the user about available placeholders.
     *
     * @return array<int|string, string>
     */
    public function placeholders(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function getPlaceholders(): array
    {
        $mapped = [];

        foreach ($this->placeholders() as $key => $value) {
            if (is_int($key)) {
                $mapped[$value] = '-';
            } else {
                $mapped[$key] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Returns an HTML string with the placeholders formatted for display.
     *
     * @return HtmlString
     */
    public function getPlaceholdersHtmlString(): HtmlString
    {
        $formPlaceholders = [
            ...array_keys($this->getPlaceholders()),
            ...$this->getDefaultPlaceholders(),
        ];

        $placeholders = array_map(
            fn ($value) => '{{ $' . $value . ' }}',
            $formPlaceholders
        );

        return new HtmlString('<p>' . __('filament-form-builder::general.you_can_use_placeholders') . '<br/><br/>' . implode('<br/>', $placeholders));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Find the correct attribute label for a given key.
     *
     * @param string $key
     * @return string
     */
    public function findAttributeForKey(string $key): string
    {
        $attributes = $this->attributes();

        if (array_key_exists($key, $attributes)) {
            return $attributes[$key];
        }

        return Lang::has($transKey = "filament-form-builder::fields.{$key}")
            ? __($transKey)
            : ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Fully modify the response after a successful form submission.
     *
     * @param FormSubmission $submission
     * @return mixed
     */
    public static function successResponse(FormSubmission $submission): mixed
    {
        return null;
    }

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
     * Hook to perform actions after a form submission is created.
     *
     * @param FormSubmission $submission
     * @return void
     */
    public static function afterSubmissionCreated(FormSubmission $submission): void
    {
        //
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
        return $submission->getFormattedData(true);
    }
}
