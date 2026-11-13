<?php

namespace VanOns\FilamentFormBuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A date no earlier and no later than its field allows. The message names the
 * date the way the field shows dates, where Laravel's `after_or_equal` would
 * name it as `2026-11-13`, or as "today".
 */
class DateBetween implements ValidationRule
{
    /**
     * @param  Closure(string): string  $format
     */
    public function __construct(
        protected ?string $min,
        protected ?string $max,
        protected Closure $format,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Dates in this format compare as text; anything else is for `date_format` to turn away.
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return;
        }

        if ($this->min !== null && $value < $this->min) {
            $fail('filament-form-builder::fields.date_too_early')->translate(['date' => ($this->format)($this->min)]);
        } elseif ($this->max !== null && $value > $this->max) {
            $fail('filament-form-builder::fields.date_too_late')->translate(['date' => ($this->format)($this->max)]);
        }
    }
}
