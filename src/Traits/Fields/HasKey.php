<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;

trait HasKey
{
    public ?string $key;
    public static string $keyPrefix = 'key_';

    /** @var array<string> */
    public static array $disallowedKeyCharacters = ['.', '*', ' '];

    protected function generateKey(): string
    {
        return Str::snake(
            $this->getLabel()
        );
    }

    public static function cleanKey(string $key): string
    {
        return str_replace(static::$disallowedKeyCharacters, '', $key);
    }

    public static function getKeyHelperText(Get $get, ?string $state): ?string
    {
        $key = static::cleanKey(Str::snake($get('key') ?? $state ?? ''));
        $prefix = static::$keyPrefix;

        return empty($key)
            ? null
            : __('filament-form-builder::fields.key') . ": {$prefix}{$key}";
    }

    public static function getKeyValidationRule(): string
    {
        return 'not_regex:/[' . preg_quote(implode('', static::$disallowedKeyCharacters), '/') . '\s]/';
    }

    public function getKey(): string
    {
        $key = !isset($this->key)
            ? $this->key = $this->generateKey()
            : $this->key;

        return static::$keyPrefix . static::cleanKey($key);
    }
}
