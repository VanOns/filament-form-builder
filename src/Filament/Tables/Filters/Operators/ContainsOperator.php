<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators;

use Filament\QueryBuilder\Constraints\TextConstraint\Operators\ContainsOperator as BaseContainsOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Concerns\QueriesAnswers;

class ContainsOperator extends BaseContainsOperator
{
    use QueriesAnswers;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, string $qualifiedColumn): Builder
    {
        return $this->matchText($query, $qualifiedColumn, 'like', '%{text}%');
    }
}
