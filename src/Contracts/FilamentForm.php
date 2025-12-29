<?php

namespace VanOns\FilamentFormBuilder\Contracts;

use Illuminate\Support\HtmlString;

interface FilamentForm
{
    public function hasRecaptcha(): bool;

    /**
     * @return string[]
     */
    public function getDefaultPlaceholders(): array;

    public static function isCustom(): bool;

    /**
     * Fully modify the response after a successful form submission.
     *
     * @param array<string, mixed> $data
     * @return mixed
     */
    public static function successReponse(array $data = []): mixed;

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
}
