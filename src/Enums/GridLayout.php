<?php

namespace VanOns\FilamentFormBuilder\Enums;

/**
 * How freely fields may sit side by side, set for the whole project with the
 * `layout` config value.
 */
enum GridLayout: string
{
    case FULL_WIDTH = 'full_width';
    case TWO_COLUMNS = 'two_columns';
    case FLEXIBLE = 'flexible';

    public static function current(): self
    {
        $layout = config('filament-form-builder.layout');

        return $layout instanceof self ? $layout : (self::tryFrom((string) $layout) ?? self::FLEXIBLE);
    }

    /**
     * @return list<FieldWidth>
     */
    public function widths(): array
    {
        return match ($this) {
            self::FULL_WIDTH => [FieldWidth::FULL],
            self::TWO_COLUMNS => [FieldWidth::HALF, FieldWidth::FULL],
            self::FLEXIBLE => FieldWidth::cases(),
        };
    }
}
