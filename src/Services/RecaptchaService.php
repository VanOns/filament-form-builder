<?php

namespace VanOns\FilamentFormBuilder\Services;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use VanOns\FilamentFormBuilder\Rules\RecaptchaRule;

class RecaptchaService
{
    public static function validate(string $responseResponse): bool
    {
        if (empty($responseResponse)) {
            return false;
        }

        try {
            $recaptchaSecret = config('filament-form-builder.recaptcha.secret');

            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $recaptchaSecret,
                'response' => $responseResponse,
            ]);

            $data = $response->json();

            return $data['success'] ?? false;
        } catch (\Exception) {
            return false;
        }
    }

    public static function checkEnabled(): bool
    {
        return config('filament-form-builder.recaptcha.enabled') && !empty(config('filament-form-builder.recaptcha.key')) && !empty(config('filament-form-builder.recaptcha.secret'));
    }

    /**
     * @return array<string, string|ValidationRule>
     */
    public static function getRules(): array
    {
        return self::checkEnabled()
            ? ['g-recaptcha-response' => ['required', 'string', new RecaptchaRule()]]
            : [];
    }
}
