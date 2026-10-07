<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Illuminate\Contracts\Validation\ValidationRule;
use VanOns\FilamentFormBuilder\Rules\RecaptchaRule;
use VanOns\FilamentFormBuilder\Services\RecaptchaService;

class RecaptchaField extends CaptchaField
{
    public static string $view = 'filament-form-builder::components.fields.recaptcha-field';
    public static string $previewView = 'filament-form-builder::filament.previews.recaptcha';

    public const KEY = 'g-recaptcha-response';

    public ?string $key = self::KEY;

    public static function isAvailable(): bool
    {
        return RecaptchaService::checkEnabled();
    }

    protected static function rule(): ValidationRule
    {
        return new RecaptchaRule();
    }
}
