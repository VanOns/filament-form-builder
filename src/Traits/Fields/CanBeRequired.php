<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait CanBeRequired
{
    public ?bool $required = false;

    public function required(bool $condition = true): static
    {
        $this->required = $condition;

        return $this;
    }

    public function isRequired(): bool
    {
        return !!$this->required;
    }
}
