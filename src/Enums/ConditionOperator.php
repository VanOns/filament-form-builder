<?php

namespace VanOns\FilamentFormBuilder\Enums;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

enum ConditionOperator: string
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
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $operator) => [$operator->value => $operator->getLabel()])
            ->all();
    }

    public function needsValue(): bool
    {
        return $this === self::EQUALS || $this === self::NOT_EQUALS;
    }

    /**
     * A field with several answers, like a multiple choice, equals a value
     * when that value is among them.
     */
    public function matches(mixed $actual, ?string $expected): bool
    {
        $answers = array_map(
            fn (mixed $answer): string => (string) $answer,
            array_filter(Arr::wrap($actual), fn (mixed $answer): bool => $answer !== null && $answer !== ''),
        );

        return match ($this) {
            self::EQUALS => in_array((string) $expected, $answers, true),
            self::NOT_EQUALS => !in_array((string) $expected, $answers, true),
            self::EMPTY => $answers === [],
            self::NOT_EMPTY => $answers !== [],
        };
    }
}
