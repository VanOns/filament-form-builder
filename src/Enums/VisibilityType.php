<?php

namespace VanOns\FilamentFormBuilder\Enums;

use Illuminate\Support\Facades\Lang;

enum VisibilityType: string
{
    case EQUALS = 'equals';
    case NOT_EQUALS = 'not_equals';
    case EMPTY = 'empty';
    case NOT_EMPTY = 'not_empty';

    public function getLabel(): string
    {
        $langKey = 'filament-form-builder::fields.' . $this->value;
        if (Lang::has($langKey)) {
            return __($langKey);
        }

        return $this->value;
    }

    /**
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->getLabel()])
            ->toArray();
    }

    public function formatString(?string $string = null): string
    {
        $string ??= '';

        return match ($this) {
            self::EQUALS => $string,
            self::NOT_EQUALS => '__not__' . $string,
            self::EMPTY => '__empty__',
            self::NOT_EMPTY => '__not_empty__',
        };
    }

    public function getRequiredRule(?string $key, ?string $value): ?string
    {
        if (!$key) {
            return null;
        }

        return match ($this) {
            self::EQUALS => "required_if:{$key},{$value}",
            self::NOT_EQUALS => "required_unless:{$key},{$value}",
            self::EMPTY => "required_if:{$key},",
            self::NOT_EMPTY => "required_unless:{$key},",
        };
    }

}
