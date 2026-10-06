<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators;

use Filament\QueryBuilder\Constraints\Operators\IsFilledOperator as BaseIsFilledOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Concerns\QueriesAnswers;

class IsFilledOperator extends BaseIsFilledOperator
{
    use QueriesAnswers;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, string $qualifiedColumn): Builder
    {
        // A list nobody ticked anything in is stored as [].
        $filled = fn (Builder $query) => $query
            ->whereNotNull($qualifiedColumn)
            ->whereNotIn($this->answerText($query, $qualifiedColumn), ['', '[]']);

        return $this->isInverse() ? $query->whereNot($filled) : $query->where($filled);
    }
}
