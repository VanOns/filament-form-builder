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
        $formPlaceholders = array_unique([
            ...$this->getFieldPlaceholders(),
            ...array_keys($this->getPlaceholders()),
            ...$this->getDefaultPlaceholders(),
        ]);

        return array_map(
            fn ($value) => '{{ $' . $value . ' }}',
            array_values($formPlaceholders)
        );
    }

    /**
     * The keys of the form's own fields, which are placeholders too. Without
     * these the list only shows what the template declares, leaving an editor
     * to guess how a field is spelled.
     *
     * Filtering here rather than through `getFields(inputsOnly: true)`: that
     * result is cached on the form and the flag only counts on the first call,
     * so asking for a subset would hand the same subset to the renderer.
     *
     * @return array<int, string>
     */
    protected function getFieldPlaceholders(): array
    {
        $inputs = array_filter(
            $this->form->getFields(),
            fn ($field) => $field::isInput()
        );

        return array_values(array_map(fn ($field) => $field->getKey(), $inputs));
    }
}
