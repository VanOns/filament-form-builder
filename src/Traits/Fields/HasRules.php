<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use VanOns\FilamentFormBuilder\Rules\RequiredWhenShown;

trait HasRules
{
    /**
     * @return array<mixed>
     */
    protected function getDefaultRules(): array
    {
        // A visitor cannot fill in a field they never see.
        if (! $this->isRequired() || $this->isHidden()) {
            return [];
        }

        $conditions = $this->getConditions();

        return [$conditions->isEmpty() ? 'required' : new RequiredWhenShown($conditions)];
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function rules(): array
    {
        return $this->getDefaultRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        return [
            $this->getKey() => !empty($rules = $this->rules())
                ? $rules
                : null,
        ];
    }
}
