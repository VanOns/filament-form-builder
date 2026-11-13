<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class DropdownField extends ChoiceField
{
    public static string $view = 'filament-form-builder::components.fields.dropdown-field';
    public static string $previewView = 'filament-form-builder::filament.previews.dropdown';

    public ?string $placeholder = null;

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public static function allowsMultiple(): bool
    {
        return false;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedChevronUpDown;
    }

    public static function editableSettings(): array
    {
        return [...parent::editableSettings(), 'placeholder'];
    }

    public static function getFields(): array
    {
        return [
            ...parent::getFields(),
            TextInput::make('placeholder'),
        ];
    }
}
