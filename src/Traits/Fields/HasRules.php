<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait HasRules
{
    /**
     * @return array<mixed>
     */
    protected function getDefaultRules(): array
    {
        return array_filter([
            $this->isRequired()
                ? 'required'
                : null,
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