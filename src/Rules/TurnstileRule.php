<?php

namespace VanOns\FilamentFormBuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use VanOns\FilamentFormBuilder\Services\TurnstileService;

class TurnstileRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! TurnstileService::validate($value, request()->ip())) {
            $fail(__('filament-form-builder::fields.invalid_turnstile'));
        }
    }
}
