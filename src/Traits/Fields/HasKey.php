<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Illuminate\Support\Str;

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
        '_token', '_method', 'g-recaptcha-response',
        'form_title', 'all_fields', 'submission_id', 'submitted_at', 'submission_url',
    ];

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
}
