<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Illuminate\Support\Str;

trait HasKey
{
    public ?string $key;

    protected function generateKey(): string
    {
        return Str::snake(
            $this->getLabel()
        );
    }

    public function getKey(): string
    {
        return !isset($this->key)
            ? $this->key = $this->generateKey()
            : $this->key;
    }
}