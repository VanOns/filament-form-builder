<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait CanBeRequired
{
    public ?bool $required = false;

    public function isRequired(): bool
    {
        return !!$this->required;
    }
}