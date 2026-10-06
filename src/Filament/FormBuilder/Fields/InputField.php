<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\TextInput;

abstract class InputField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.input-field';

    public ?string $placeholder = null;

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    abstract public function getInputType(): string;

    /**
     * @return array<int, mixed>
     */
    protected function getTypeRules(): array
    {
        return [];
    }

    protected function fieldRules(): array
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
