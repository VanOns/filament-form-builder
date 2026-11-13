<?php

namespace VanOns\FilamentFormBuilder\Helpers;

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;

class FieldTypeHelper extends TypeHelper
{
    /**
     * @return array<string, class-string<FormField>>
     */
    public static function all(): array
    {
        return static::configured('fields', FormField::class);
    }

    /**
     * @return class-string<FormField>|null
     */
    public static function resolve(mixed $type): ?string
    {
        return is_string($type) ? (static::all()[$type] ?? null) : null;
    }
}
