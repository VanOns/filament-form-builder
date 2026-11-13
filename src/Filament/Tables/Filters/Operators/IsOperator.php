<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators;

use Filament\QueryBuilder\Constraints\SelectConstraint\Operators\IsOperator as BaseIsOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Concerns\QueriesAnswers;

class IsOperator extends BaseIsOperator
{
    use QueriesAnswers;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, string $qualifiedColumn): Builder
    {
        $values = $this->getValues();

        if ($values === []) {
            return $query;
        }

        return $this->matchAnswer(
            $query,
            $qualifiedColumn,
            fn (Builder $query) => $query->whereIn($qualifiedColumn, $values),
        );
    }

    /**
     * Answers are stored as text, which Postgres will not compare to a number.
     *
     * @return list<string>
     */
    protected function getValues(): array
    {
        return array_map(strval(...), Arr::wrap($this->getValueSetting()));
    }
}
