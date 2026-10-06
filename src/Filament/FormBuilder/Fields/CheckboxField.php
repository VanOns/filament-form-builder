<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

class CheckboxField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.checkbox-field';
    public static string $previewView = 'filament-form-builder::filament.previews.checkbox';

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedCheckCircle;
    }

    public static function getDefaultValueComponent(): ?Component
    {
        return Checkbox::make('defaultValue')
            ->label(__('filament-form-builder::fields.checked_by_default'));
    }

    public static function canBeHidden(): bool
    {
        return false;
    }

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
