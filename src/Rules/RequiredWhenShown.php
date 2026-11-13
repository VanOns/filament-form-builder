<?php

namespace VanOns\FilamentFormBuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use VanOns\FilamentFormBuilder\Classes\FieldConditions;

/**
 * Required only while the field's conditions show it, which no combination of
 * Laravel's required_if rules can express once there are several of them.
 */
class RequiredWhenShown implements DataAwareRule, ValidationRule
{
    public bool $implicit = true;

    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    public function __construct(protected FieldConditions $conditions)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) && $this->conditions->passes($this->data)) {
            $fail('validation.required')->translate();
        }
    }
}
