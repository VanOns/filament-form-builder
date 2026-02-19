<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Placeholder;
use VanOns\FilamentFormBuilder\Rules\RecaptchaRule;
use VanOns\FilamentFormBuilder\Services\RecaptchaService;

class RecaptchaField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.recaptcha-field';

    public ?string $key = 'g-recaptcha-response';
    public static string $keyPrefix = '';

    protected function rules(): array
    {
        return RecaptchaService::checkEnabled()
            ? ['required', 'string', new RecaptchaRule()]
            : [];
    }

    public static function getFields(): array
    {
        return [
            Placeholder::make('recaptcha')
                ->columnStart(1)
                ->hiddenLabel()
                ->content(__('filament-form-builder::general.no_settings')),
        ];
    }

    public static function isInput(): bool
    {
        return false;
    }

    public static function hasVisibilitySettings(): bool
    {
        return false;
    }
}
