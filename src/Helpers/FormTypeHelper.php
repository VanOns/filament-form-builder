<?php

namespace VanOns\FilamentFormBuilder\Helpers;

use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Models\Form;

class FormTypeHelper
{
    /**
     * The configured form types under the names a form stores.
     *
     * @return array<string, class-string<FormType>>
     */
    public static function all(): array
    {
        $types = [];

        foreach ((array) config('filament-form-builder.types', []) as $name => $class) {
            if (is_string($name) && is_string($class) && is_subclass_of($class, FormType::class)) {
                $types[$name] = $class;
            }
        }

        return $types;
    }

    /**
     * @return class-string<FormType>|null
     */
    public static function resolve(mixed $type): ?string
    {
        return is_string($type) ? (static::all()[$type] ?? null) : null;
    }

    /**
     * The type for the given form, or for a form that is still being made.
     */
    public static function make(mixed $type, ?Form $form = null): ?FormType
    {
        $class = static::resolve($type);

        return $class === null ? null : new $class($form ?? new Form(['template' => $type]));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(fn (string $class): string => $class::getLabel(), static::all());
    }
}
