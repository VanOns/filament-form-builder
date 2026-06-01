<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use Illuminate\Support\HtmlString;

trait HasPlaceholders
{
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
     * Set fallback values for empty fields and used to inform the user about available placeholders.
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
}
