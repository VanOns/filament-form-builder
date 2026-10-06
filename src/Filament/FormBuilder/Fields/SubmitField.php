<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;

class SubmitField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.submit-field';
    public static string $previewView = 'filament-form-builder::filament.previews.submit';

    protected function fieldRules(): array
    {
        return [];
    }

    public static function paletteGroup(): string
    {
        return 'layout';
    }

    public static function minWidth(): FieldWidth
    {
        return FieldWidth::FULL;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedPaperAirplane;
    }

    public static function getFields(): array
    {
        return [
            TextInput::make('label')
                ->label(__('filament-form-builder::fields.label'))
                ->required(),
        ];
    }

    public static function isInput(): bool
    {
        return false;
    }

    public static function hasConditionSettings(): bool
    {
        return false;
    }
}
