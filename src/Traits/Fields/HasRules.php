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
        // An empty answer arrives as null, which rules like `email` or `in` would
        // reject. A visitor cannot fill in a field they never see either.
        if (! $this->isRequired() || $this->isHidden()) {
            return ['nullable'];
        }

        $conditions = $this->getConditions();

        return $conditions->isEmpty()
            ? ['required']
            : [new RequiredWhenShown($conditions), 'nullable'];
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
