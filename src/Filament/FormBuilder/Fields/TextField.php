<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\TextInput;

class TextField extends Field
{
    public static string $view = 'filament-form-builder::components.fields.text-field';

    public ?string $placeholder;

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
            TextInput::make('placeholder'),
        ];
    }
}
