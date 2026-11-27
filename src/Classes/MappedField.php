<?php

namespace VanOns\FilamentFormBuilder\Classes;

/**
 * A field an integration asks for, which the form maps to one of its own
 * fields, or with text() to a line of text with merge tags.
 *
 * @phpstan-consistent-constructor
 */
class MappedField
{
    protected bool $isRequired = false;

    protected bool $isText = false;

    protected bool $isEmail = false;

    protected ?string $helperText = null;

    public function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {
    }

    public static function make(string $key, string $label): static
    {
        return new static($key, $label);
    }

    public function required(bool $condition = true): static
    {
        $this->isRequired = $condition;

        return $this;
    }

    public function text(bool $condition = true): static
    {
        $this->isText = $condition;

        return $this;
    }

    /**
     * Only offers the fields that hold an e-mail address.
     */
    public function email(bool $condition = true): static
    {
        $this->isEmail = $condition;

        return $this;
    }

    public function helperText(?string $text): static
    {
        $this->helperText = $text;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function isText(): bool
    {
        return $this->isText;
    }

    public function isEmail(): bool
    {
        return $this->isEmail;
    }

    public function getHelperText(): ?string
    {
        return $this->helperText;
    }
}
