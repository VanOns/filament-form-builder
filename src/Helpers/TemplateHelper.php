<?php

namespace VanOns\FilamentFormBuilder\Helpers;

use VanOns\FilamentFormBuilder\Contracts\FilamentForm;

class TemplateHelper
{
    /**
     * Whether the given value is a form template class.
     *
     * @phpstan-assert-if-true class-string<FilamentForm> $template
     */
    public static function isTemplate(mixed $template): bool
    {
        return is_string($template) && is_subclass_of($template, FilamentForm::class);
    }

    /**
     * The given value as a form template class, or null when it isn't one.
     *
     * @return class-string<FilamentForm>|null
     */
    public static function resolve(mixed $template): ?string
    {
        return static::isTemplate($template) ? $template : null;
    }

    /**
     * The number of grid columns for the given template, falling back to the
     * configured default when the value isn't a template class.
     */
    public static function columns(mixed $template): int
    {
        $class = static::resolve($template);

        return $class !== null
            ? $class::columns()
            : (int) config('filament-form-builder.columns', 2);
    }
}
