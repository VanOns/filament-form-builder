<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Classes\Honeypot;
use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\RecaptchaField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TurnstileField;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;

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
    public static array $reservedKeys = ['_token', '_method', RecaptchaField::KEY, TurnstileField::KEY];

    /**
     * @return array<string>
     */
    public static function reservedKeys(): array
    {
        return [
            ...static::$reservedKeys,
            ...array_keys(SubmissionPlaceholders::BUILT_IN),
            ...array_keys(FilamentFormBuilderPlugin::getCustomMergeTags()),
            ...Honeypot::keys(),
        ];
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
