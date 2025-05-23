<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Component;

class CheckboxField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.checkbox-field';

    /**
     * @return array<Component>
     */
    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
        ];
    }
}
