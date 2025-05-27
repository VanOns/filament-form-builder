<?php

namespace VanOns\FilamentFormBuilder\Traits;

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;

trait HasCustomFields
{
    /**
     * @return array<string, FormField>
     */
    public function getFields(bool $inputsOnly = false): array
    {
        if (!isset($this->fields) && $this->isCustom()) {
            $fields = $this->custom['fields'] ?? [];

            $fieldInstaces = [];
            foreach ($fields as $field) {
                /* @var class-string<FormField> $type */
                if (($type = $field['fieldType'] ?? null)) {
                    if ($inputsOnly && !$type::isInput()) {
                        continue;
                    }
                    /* @var FormField $fieldInstace */
                    $fieldInstace = new $type($field);
                    $fieldInstaces[] = $fieldInstace;
                }
            }

            $this->fields = $fieldInstaces;
        } elseif (!$this->template::isCustom()) {
            $this->fields = [];
        }

        return $this->fields;
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
            $labels[$field->getKey()] = $field->getLabel();
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
        return $this->template::isCustom();
    }
}