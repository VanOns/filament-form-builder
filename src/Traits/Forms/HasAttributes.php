<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use Illuminate\Support\Facades\Lang;

trait HasAttributes
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Find the correct attribute label for a given key.
     *
     * @param string $key
     * @return string
     */
    public function findAttributeForKey(string $key): string
    {
        $attributes = $this->attributes();

        if (array_key_exists($key, $attributes)) {
            return $attributes[$key];
        }

        return Lang::has($transKey = "filament-form-builder::fields.{$key}")
            ? __($transKey)
            : ucfirst(str_replace('_', ' ', $key));
    }
}