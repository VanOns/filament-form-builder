<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class InputField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.input-field';

    public string $inputType = 'text';
    public ?string $placeholder;

    protected function rules(): array
    {
        $type = $this->inputType;
        return match ($type) {
            'email' => ['email'],
            'number' => ['numeric'],
            'tel' => ['phone'],
            default => [],
        };
    }

    public static function getFields(): array
    {
        return [
            Select::make('inputType')
                ->label(__('filament-form-builder::fields.input_type'))
                ->required()
                ->options([
                    'text' => 'Text',
                    'number' => 'Number',
                    'email' => 'Email',
                    'tel' => 'Telephone',
                ]),
            ...static::getDefaultFields(),
            TextInput::make('placeholder'),
        ];
    }
}
