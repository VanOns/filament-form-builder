<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators;

use Filament\QueryBuilder\Constraints\NumberConstraint\Operators\EqualsOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Concerns\QueriesAnswers;

class NumberEqualsOperator extends EqualsOperator
{
    use QueriesAnswers;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, string $qualifiedColumn): Builder
    {
        if ($this->getNumericSetting('number') === null) {
            return $query;
        }

        return $this->matchAnswer(
            $query,
            $qualifiedColumn,
            fn (Builder $query) => $this->compareNumber($query, $qualifiedColumn, '='),
        );
    }
}
