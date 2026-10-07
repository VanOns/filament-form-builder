<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables\Filters\Concerns;

use Closure;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;

/**
 * Filament's operators compare a column as it is. An answer sits inside JSON,
 * where MySQL compares case-sensitively and a number is stored as text.
 */
trait QueriesAnswers
{
    /**
     * @param  Builder<Model>  $query
     */
    protected function answerNumber(Builder $query, string $column): ExpressionContract
    {
        $answer = 'nullif(' . $query->getQuery()->getGrammar()->wrap($column) . ", '')";

        return new Expression(match ($query->getModel()->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => "cast({$answer} as decimal(65, 10))",
            'pgsql' => "cast({$answer} as numeric)",
            default => "cast({$answer} as real)",
        });
    }

    /**
     * The inverse of a rule also matches the submissions without an answer:
     * "does not contain Jan" includes the ones that left the name empty.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function matchAnswer(Builder $query, string $column, Closure $match): Builder
    {
        if (!$this->isInverse()) {
            return $query->where($match);
        }

        return $query->where(fn (Builder $query) => $query->whereNot($match)->orWhereNull($column));
    }

    /**
     * @param  Builder<Model>  $query
     * @param  string  $pattern  with `{text}` where the typed text goes
     * @return Builder<Model>
     */
    protected function matchText(Builder $query, string $column, string $operator, string $pattern): Builder
    {
        $text = $this->getStringSetting('text');

        if ($text === null) {
            return $query;
        }

        $value = str_replace('{text}', mb_strtolower($text), $pattern);

        return $this->matchAnswer(
            $query,
            $column,
            fn (Builder $query) => $query->where(AnswerConstraints::lowered($query, $column), $operator, $value),
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function compareNumber(Builder $query, string $column, string $operator): Builder
    {
        $number = $this->getNumericSetting('number');

        if ($number === null) {
            return $query;
        }

        // MySQL reads a JSON null as the text "null", which casts to 0.
        return $query->where(fn (Builder $query) => $query
            ->whereNotNull($column)
            ->where($this->answerNumber($query, $column), $operator, $number));
    }
}
