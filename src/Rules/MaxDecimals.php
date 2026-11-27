<?php

namespace VanOns\FilamentFormBuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A number with at most so many decimals. Laravel's `decimal` says a visitor
 * "must have 0-2 decimal places", and a project's own language files often
 * lack it; this one brings a message of its own.
 */
class MaxDecimals implements ValidationRule
{
    public function __construct(protected int $decimals)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || preg_match('/^[+-]?\d*(?:\.(\d*))?$/', (string) $value, $matches) !== 1) {
            $fail('validation.numeric')->translate();

            return;
        }

        if (strlen($matches[1] ?? '') > $this->decimals) {
            $fail('filament-form-builder::fields.max_decimals')->translateChoice($this->decimals, ['count' => $this->decimals]);
        }
    }
}
