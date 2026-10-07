<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators;

use Filament\QueryBuilder\Constraints\Operators\Operator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxField;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\Concerns\QueriesAnswers;

class IsCheckedOperator extends Operator
{
    use QueriesAnswers;

    public function getName(): string
    {
        return 'isChecked';
    }

    public function getLabel(): string
    {
        return __($this->isInverse()
            ? 'filament-form-builder::general.filters.is_checked.label.inverse'
            : 'filament-form-builder::general.filters.is_checked.label.direct');
    }

    public function getSummary(): string
    {
        return __(
            $this->isInverse()
                ? 'filament-form-builder::general.filters.is_checked.summary.inverse'
                : 'filament-form-builder::general.filters.is_checked.summary.direct',
            ['attribute' => $this->getConstraint()?->getAttributeLabel()],
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, string $qualifiedColumn): Builder
    {
        return $this->matchAnswer(
            $query,
            $qualifiedColumn,
            fn (Builder $query) => $query->whereIn(AnswerConstraints::lowered($query, $qualifiedColumn), CheckboxField::CHECKED_VALUES),
        );
    }
}
