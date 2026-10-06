<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators;

use Filament\QueryBuilder\Constraints\NumberConstraint\Operators\IsMinOperator as BaseIsMinOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Concerns\QueriesAnswers;

class IsMinOperator extends BaseIsMinOperator
{
    use QueriesAnswers;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, string $qualifiedColumn): Builder
    {
        return $this->compareNumber($query, $qualifiedColumn, $this->isInverse() ? '<' : '>=');
    }
}
