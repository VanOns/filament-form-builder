<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

trait HasColumns
{
    /**
     * The number of grid columns the form is rendered with. Override in a form
     * template to deviate from the configured default.
     */
    public static function columns(): int
    {
        return (int) config('filament-form-builder.columns', 2);
    }
}
