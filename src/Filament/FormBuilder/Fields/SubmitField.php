<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\TextInput;

class SubmitField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.submit-field';

    protected function rules(): array
    {
        return [];
    }

    public static function getFields(): array
    {
        return [
            TextInput::make('label')
                ->label(__('filament-form-builder::fields.label'))
                ->required(),
        ];
    }
}
