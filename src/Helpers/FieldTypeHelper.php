<?php

namespace VanOns\FilamentFormBuilder\Helpers;

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;

class FieldTypeHelper
{
    /**
     * The configured field types under the names a form stores, so a field
     * class can be renamed or moved without touching the stored forms.
     *
     * @return array<string, class-string<FormField>>
     */
    public static function all(): array
    {
        $types = [];

        foreach ((array) config('filament-form-builder.fields', []) as $name => $class) {
            if (is_string($name) && is_string($class) && is_subclass_of($class, FormField::class)) {
                $types[$name] = $class;
            }
        }

        return $types;
    }

    /**
     * The name a field class is configured under, or null for one that is not.
     */
    public static function nameOf(string $class): ?string
    {
        $name = array_search($class, static::all(), true);

        return is_string($name) ? $name : null;
    }

    /**
     * @return class-string<FormField>|null
     */
    public static function resolve(mixed $type): ?string
    {
        return is_string($type) ? (static::all()[$type] ?? null) : null;
    }
}
