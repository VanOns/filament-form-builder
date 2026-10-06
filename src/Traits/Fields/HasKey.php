<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Illuminate\Support\Str;

trait HasKey
{
    public ?string $key = null;
    public static string $keyPrefix = 'key_';

    /**
     * @var array<string>
     */
    public static array $disallowedKeyCharacters = ['.', '*', ' '];

    public static function cleanKey(string $key): string
    {
        return str_replace(static::$disallowedKeyCharacters, '', $key);
    }

    public static function getKeyValidationRule(): string
    {
        return 'not_regex:/[' . preg_quote(implode('', static::$disallowedKeyCharacters), '/') . '\s]/';
    }

    public function getBaseKey(): string
    {
        return static::cleanKey(filled($this->key) ? $this->key : Str::snake($this->label ?? class_basename(static::class)));
    }

    public function getKey(): string
    {
        return static::$keyPrefix . $this->getBaseKey();
    }
}
