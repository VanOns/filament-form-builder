<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

trait HasRules
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultRules(): array
    {
        return [
            ...$this->getRecaptchaRules(),
            ...$this->rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getRules(): array
    {

        return $this->getDefaultRules();
    }
}
