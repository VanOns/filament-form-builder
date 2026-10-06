<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables\Filters;

use Filament\Tables\Filters\QueryBuilder\Constraints\Constraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators\ContainsAnyOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators\ContainsOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators\EndsWithOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators\EqualsOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators\IsCheckedOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators\IsFilledOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators\IsMaxOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators\IsMinOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators\IsOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators\NumberEqualsOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators\StartsWithOperator;

/**
 * The rules the submissions table offers per answer, named after the key the
 * answer is stored under.
 */
final class AnswerConstraints
{
    public static function text(string $key, string $label): TextConstraint
    {
        return TextConstraint::make($key)
            ->label($label)
            ->attribute("data->{$key}")
            ->operators([
                ContainsOperator::class,
                StartsWithOperator::class,
                EndsWithOperator::class,
                EqualsOperator::class,
                IsFilledOperator::class,
            ]);
    }

    public static function number(string $key, string $label): NumberConstraint
    {
        return NumberConstraint::make($key)
            ->label($label)
            ->attribute("data->{$key}")
            ->operators([
                NumberEqualsOperator::class,
                IsMinOperator::class,
                IsMaxOperator::class,
                IsFilledOperator::class,
            ]);
    }

    /**
     * @param  array<string, string>  $options  value => label
     */
    public static function choice(string $key, string $label, array $options, bool $storesList = false): SelectConstraint
    {
        return SelectConstraint::make($key)
            ->label($label)
            ->attribute("data->{$key}")
            ->options($options)
            ->multiple()
            ->operators([
                $storesList ? ContainsAnyOperator::class : IsOperator::class,
                IsFilledOperator::class,
            ]);
    }

    public static function checkbox(string $key, string $label): Constraint
    {
        return Constraint::make($key)
            ->label($label)
            ->attribute("data->{$key}")
            ->operators([IsCheckedOperator::class]);
    }

    public static function file(string $key, string $label): Constraint
    {
        return Constraint::make($key)
            ->label($label)
            ->attribute("files->{$key}")
            ->operators([IsFilledOperator::class]);
    }
}
