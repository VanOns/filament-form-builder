<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\TextInput;

class TextAreaField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.text-area-field';

    public ?string $placeholder = null;
    public ?int $rows = null;

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
            TextInput::make('placeholder'),
            TextInput::make('rows')
                ->label(__('filament-form-builder::fields.rows'))
                ->numeric(),
        ];
    }
}
