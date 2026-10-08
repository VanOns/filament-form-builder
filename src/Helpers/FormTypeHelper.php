<?php

namespace VanOns\FilamentFormBuilder\Helpers;

use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Models\Form;

class FormTypeHelper extends TypeHelper
{
    /**
     * @return array<string, class-string<FormType>>
     */
    public static function all(): array
    {
        return static::configured('types', FormType::class);
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
     * The type of a form built on the canvas.
     */
    public const CUSTOM = 'custom';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(fn (string $class): string => $class::getLabel(), static::all());
    }
}
