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
    case GREATER_THAN = 'greater_than';
    case AT_LEAST = 'at_least';
    case LESS_THAN = 'less_than';
    case AT_MOST = 'at_most';

    public function getLabel(): string
    {
        $langKey = 'filament-form-builder::fields.' . $this->value;
        if (Lang::has($langKey)) {
            return __($langKey);
        }

        return $this->value;
    }

    /**
     * @param  list<self>|null  $operators  all of them by default
     * @return array<string, string>
     */
    public static function options(?array $operators = null): array
    {
        return collect($operators ?? self::cases())
            ->mapWithKeys(fn (self $operator) => [$operator->value => $operator->getLabel()])
            ->all();
    }

    /**
     * What any answer can be tested on.
     *
     * @return list<self>
     */
    public static function basic(): array
    {
        return [self::EQUALS, self::NOT_EQUALS, self::EMPTY, self::NOT_EMPTY];
    }

    /**
     * What a number can be tested on as well.
     *
     * @return list<self>
     */
    public static function numeric(): array
    {
        return [self::EQUALS, self::NOT_EQUALS, self::GREATER_THAN, self::AT_LEAST, self::LESS_THAN, self::AT_MOST, self::EMPTY, self::NOT_EMPTY];
    }

    public function needsValue(): bool
    {
        return !in_array($this, [self::EMPTY, self::NOT_EMPTY], true);
    }

    /**
     * A field with several answers, like a multiple choice, equals a value
     * when that value is among them.
     */
    public function matches(mixed $actual, ?string $expected): bool
    {
        $answers = array_values(array_map(
            fn (mixed $answer): string => (string) $answer,
            array_filter(Arr::wrap($actual), fn (mixed $answer): bool => $answer !== null && $answer !== ''),
        ));
        $comparison = static::compare($answers, $expected);

        return match ($this) {
            self::EQUALS => in_array((string) $expected, $answers, true),
            self::NOT_EQUALS => !in_array((string) $expected, $answers, true),
            self::EMPTY => $answers === [],
            self::NOT_EMPTY => $answers !== [],
            self::GREATER_THAN => $comparison !== null && $comparison > 0,
            self::AT_LEAST => $comparison !== null && $comparison >= 0,
            self::LESS_THAN => $comparison !== null && $comparison < 0,
            self::AT_MOST => $comparison !== null && $comparison <= 0,
        };
    }

    /**
     * How the answer relates to the value as numbers; without two numbers
     * nothing holds, not even "at most".
     *
     * @param  list<string>  $answers
     */
    private static function compare(array $answers, ?string $expected): ?int
    {
        $answer = $answers[0] ?? null;

        if (!is_numeric($answer) || !is_numeric($expected)) {
            return null;
        }

        return (float) $answer <=> (float) $expected;
    }
}
