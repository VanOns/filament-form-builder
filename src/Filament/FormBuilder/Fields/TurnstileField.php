<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;
use VanOns\FilamentFormBuilder\Rules\TurnstileRule;
use VanOns\FilamentFormBuilder\Services\TurnstileService;

class TurnstileField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.turnstile-field';
    public static string $previewView = 'filament-form-builder::filament.previews.turnstile';

    public ?string $key = 'cf-turnstile-response';

    protected function fieldRules(): array
    {
        return TurnstileService::checkEnabled()
            ? ['required', 'string', new TurnstileRule()]
            : [];
    }

    public static function isAvailable(): bool
    {
        return TurnstileService::checkEnabled();
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

    public static function hasConditionSettings(): bool
    {
        return false;
    }
}
