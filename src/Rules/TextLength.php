<?php

namespace VanOns\FilamentFormBuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A text between a minimum and a maximum number of characters, with a line
 * break counted as one: `minlength` and `maxlength` in the browser count it
 * so, while a form posts it as two.
 */
class TextLength implements ValidationRule
{
    public function __construct(protected ?int $min, protected ?int $max)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $length = mb_strlen(str_replace("\r\n", "\n", $value));

        if ($this->min !== null && $length < $this->min) {
            $fail('validation.min.string')->translate(['min' => $this->min]);
        } elseif ($this->max !== null && $length > $this->max) {
            $fail('validation.max.string')->translate(['max' => $this->max]);
        }
    }
}
