<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

class CheckboxField extends Field
{
    public static string $view = 'filament-form-builder::components.fields.checkbox-field';

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
        ];
    }
}
