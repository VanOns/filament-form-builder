<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Classes\Honeypot;

trait HasKey
{
    public ?string $key = null;

    /**
     * @var array<string>
     */
    public static array $disallowedKeyCharacters = ['.', '*', ' ', '[', ']'];

    /**
     * Names the request or the placeholders already use for something else.
     *
     * @var array<string>
     */
    public static array $reservedKeys = [
        '_token', '_method', 'g-recaptcha-response', 'cf-turnstile-response',
        'form_title', 'all_fields', 'submission_id', 'submitted_at', 'submitted_from', 'submission_url',
    ];

    /**
     * @return array<string>
     */
    public static function reservedKeys(): array
    {
        return [...static::$reservedKeys, ...Honeypot::keys()];
    }

    public function key(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public static function cleanKey(string $key): string
    {
        return str_replace(static::$disallowedKeyCharacters, '', $key);
    }

    public static function getKeyValidationRule(): string
    {
        return 'not_regex:/[' . preg_quote(implode('', static::$disallowedKeyCharacters), '/') . '\s]/';
    }

    public function getKey(): string
    {
        return static::cleanKey(filled($this->key) ? $this->key : Str::snake($this->label ?? class_basename(static::class)));
    }

    public function getInputName(): string
    {
        return $this->getKey();
    }
}
