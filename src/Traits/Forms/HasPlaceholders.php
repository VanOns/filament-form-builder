<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

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
     * Returns the available placeholders formatted for display.
     *
     * @return array<int, string>
     */
    public function getPlaceholderList(): array
    {
        $formPlaceholders = [
            ...array_keys($this->getPlaceholders()),
            ...$this->getDefaultPlaceholders(),
        ];

        return array_map(
            fn ($value) => '{{ $' . $value . ' }}',
            $formPlaceholders
        );
    }
}
