<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\TextInput;

abstract class InputField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.input-field';

    public ?string $placeholder = null;

    abstract public function getInputType(): string;

    /**
     * @return array<int, mixed>
     */
    protected function getTypeRules(): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [
            ...$this->getDefaultRules(),
            ...$this->getTypeRules(),
        ];
    }

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
            TextInput::make('placeholder'),
        ];
    }
}
