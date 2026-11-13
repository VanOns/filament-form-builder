<?php

namespace VanOns\FilamentFormBuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * At most so many files in a multiple upload. Laravel's `max` speaks of items
 * here, and a project may have worded it for choices; this speaks of files.
 */
class MaxFiles implements ValidationRule
{
    public function __construct(protected int $files)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value) && count($value) > $this->files) {
            $fail('filament-form-builder::fields.too_many_files')->translateChoice($this->files, ['count' => $this->files]);
        }
    }
}
