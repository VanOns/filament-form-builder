<?php

namespace VanOns\FilamentFormBuilder\Traits;

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;

trait HasCustomFields
{
    /**
     * @return array<int, FormField>
     */
    public function getFields(bool $inputsOnly = false): array
    {
        $this->fields ??= $this->makeFields();

        return $inputsOnly
            ? array_values(array_filter($this->fields, fn (FormField $field): bool => $field::isInput()))
            : $this->fields;
    }

    /**
     * @return array<int, FormField>
     */
    protected function makeFields(): array
    {
        if (!$this->isCustom()) {
            return [];
        }

        $fields = [];

        foreach ($this->custom['fields'] ?? [] as $data) {
            $type = $data['fieldType'] ?? null;

            if (is_string($type) && is_subclass_of($type, FormField::class)) {
                $fields[] = (new $type($data))->setGridColumns($this->getColumns());
            }
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomFormRules(): array
    {
        $rules = [];
        foreach ($this->getFields() as $field) {
            $rules = array_merge($rules, $field->getRules());
        }

        return array_filter($rules);
    }

    /**
     * @return array<string, string>
     */
    public function getCustomFormLabels(): array
    {
        $labels = [];
        foreach ($this->getFields() as $field) {
            // Every key the field fills, not just its own: a field that writes
            // several values would otherwise show them under their raw keys.
            $labels = [...$labels, ...$field->getSubmissionColumns()];
        }

        return $labels;
    }

    /**
     * @return array<string>
     */
    public function getCustomFormKeys(): array
    {
        return array_map(
            fn (FormField $field) => $field->getKey(),
            $this->getFields(inputsOnly: true)
        );
    }

    public function isCustom(): bool
    {
        $template = TemplateHelper::resolve($this->template);

        return $template !== null && $template::isCustom();
    }
}
