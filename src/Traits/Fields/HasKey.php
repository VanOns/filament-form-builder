<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Illuminate\Support\Str;

trait HasKey
{
    public ?string $key;
    public static string $keyPrefix = 'key_';

    protected function generateKey(): string
    {
        return Str::snake(
            $this->getLabel()
        );
    }

    public function getKey(): string
    {
        $key = !isset($this->key)
            ? $this->key = $this->generateKey()
            : $this->key;

        return static::$keyPrefix . $key;
    }
}