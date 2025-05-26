<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\TextInput;

class EmailField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.email-field';

    public ?string $placeholder;

    protected function rules(): array
    {
        return [
            'email',
        ];
    }

    public static function getHelperText(): ?string
    {
        return __('filament-form-builder::fields.helper_texts.submitter_email');
    }

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
            TextInput::make('placeholder'),
        ];
    }
}
