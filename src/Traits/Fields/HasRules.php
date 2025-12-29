<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait HasRules
{
    /**
     * @return array<mixed>
     */
    protected function getDefaultRules(): array
    {
        if ($this->hasVisibilityCondition() && $this->isRequired()) {
            $requiredRule = $this->getRequiredVisibilityRule();
        } else {
            $requiredRule = $this->isRequired() ? 'required' : null;
        }

        return array_filter([
            $requiredRule,
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
