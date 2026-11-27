<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Illuminate\Contracts\Validation\ValidationRule;
use VanOns\FilamentFormBuilder\Rules\TurnstileRule;
use VanOns\FilamentFormBuilder\Services\TurnstileService;

class TurnstileField extends CaptchaField
{
    public static string $view = 'filament-form-builder::components.fields.turnstile-field';
    public static string $previewView = 'filament-form-builder::filament.previews.turnstile';

    public const KEY = 'cf-turnstile-response';

    public ?string $key = self::KEY;

    public static function isAvailable(): bool
    {
        return TurnstileService::checkEnabled();
    }

    protected static function rule(): ValidationRule
    {
        return new TurnstileRule();
    }
}
