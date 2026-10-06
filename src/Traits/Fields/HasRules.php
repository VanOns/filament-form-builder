<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

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

        return array_filter([
            $this->hasVisibilityCondition() ? $this->getRequiredVisibilityRule() : 'required',
        ]);
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
