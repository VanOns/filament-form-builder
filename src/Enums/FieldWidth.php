<?php

namespace VanOns\FilamentFormBuilder\Enums;

/**
 * The widths a field can take, in columns of a 12-column grid: thirds and
 * quarters both fit, and at most four fields share a row. Which of them a
 * project offers depends on its GridLayout.
 */
enum FieldWidth: int
{
    case QUARTER = 3;
    case THIRD = 4;
    case HALF = 6;
    case TWO_THIRDS = 8;
    case THREE_QUARTERS = 9;
    case FULL = 12;

    /**
     * The widths the project's layout offers, narrowest first.
     *
     * @return list<self>
     */
    public static function available(): array
    {
        return GridLayout::current()->widths();
    }

    /**
     * The available widths a field this wide at least can take.
     *
     * @return list<self>
     */
    public static function availableFrom(self $minimum): array
    {
        return array_values(array_filter(self::available(), fn (self $width): bool => $width->value >= $minimum->value));
    }

    public function isAvailable(): bool
    {
        return in_array($this, self::available(), true);
    }

    /**
     * The widest available width within the span and not below the minimum. An
     * empty span is the full row, and one too narrow gives the narrowest the
     * minimum allows, so a stored value never overflows its row.
     */
    public static function fit(mixed $span, self $minimum = self::QUARTER): self
    {
        if (! is_numeric($span)) {
            return self::FULL;
        }

        return self::within((int) $span, $minimum) ?? self::availableFrom($minimum)[0] ?? self::FULL;
    }

    /**
     * Like fit(), but null when nothing available fits within the span.
     */
    public static function within(int $span, self $minimum = self::QUARTER): ?self
    {
        $fitting = array_values(array_filter(self::availableFrom($minimum), fn (self $width): bool => $width->value <= $span));

        return $fitting === [] ? null : $fitting[count($fitting) - 1];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::QUARTER => '¼',
            self::THIRD => '⅓',
            self::HALF => '½',
            self::TWO_THIRDS => '⅔',
            self::THREE_QUARTERS => '¾',
            self::FULL => '1/1',
        };
    }

    public function getDescription(): string
    {
        return __('filament-form-builder::general.canvas.widths.' . strtolower($this->name));
    }
}
