<?php

namespace VanOns\FilamentFormBuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use VanOns\FilamentFormBuilder\Services\RecaptchaService;

class RecaptchaRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param Closure(string, ?string=): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valid = RecaptchaService::validate($value);

        if (!$valid) {
            $fail(__('filament-form-builder::fields.invalid_recaptcha'));
        }
    }
}
