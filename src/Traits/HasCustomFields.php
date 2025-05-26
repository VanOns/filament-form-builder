<?php

namespace VanOns\FilamentFormBuilder\Traits;

trait HasCustomFields
{
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

    public function isCustom(): bool
    {
        return $this->isCustom();
    }
}