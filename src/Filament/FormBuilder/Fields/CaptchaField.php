<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Validation\ValidationRule;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;

abstract class CaptchaField extends FormField
{
    abstract protected static function rule(): ValidationRule;

    protected function fieldRules(): array
    {
        return static::isAvailable() ? ['required', 'string', static::rule()] : [];
    }

    public static function paletteGroup(): string
    {
        return 'layout';
    }

    public static function minWidth(): FieldWidth
    {
        return FieldWidth::HALF;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedShieldCheck;
    }

    public static function getFields(): array
    {
        return [
            Text::make(__('filament-form-builder::general.no_settings'))
                ->columnStart(1),
        ];
    }

    public static function isInput(): bool
    {
        return false;
    }
}
