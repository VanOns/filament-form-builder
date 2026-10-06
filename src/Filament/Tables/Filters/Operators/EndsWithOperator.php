<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators;

use Filament\QueryBuilder\Constraints\TextConstraint\Operators\EndsWithOperator as BaseEndsWithOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Concerns\QueriesAnswers;

class EndsWithOperator extends BaseEndsWithOperator
{
    use QueriesAnswers;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, string $qualifiedColumn): Builder
    {
        return $this->matchText($query, $qualifiedColumn, 'like', '%{text}');
    }
}
