<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables\Filters\Operators;

use Filament\QueryBuilder\Constraints\SelectConstraint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * For a field that stores a list: one of the chosen options is in it.
 */
class ContainsAnyOperator extends IsOperator
{
    public function getName(): string
    {
        return 'containsAny';
    }

    public function getLabel(): string
    {
        return __($this->isInverse()
            ? 'filament-query-builder::query-builder.operators.text.contains.label.inverse'
            : 'filament-query-builder::query-builder.operators.text.contains.label.direct');
    }

    public function getSummary(): string
    {
        $constraint = $this->getConstraint();
        $options = $constraint instanceof SelectConstraint ? $constraint->getOptions() : [];

        return __(
            $this->isInverse()
                ? 'filament-query-builder::query-builder.operators.text.contains.summary.inverse'
                : 'filament-query-builder::query-builder.operators.text.contains.summary.direct',
            [
                'attribute' => $constraint?->getAttributeLabel(),
                'text' => Arr::join(
                    Arr::only($options, $this->getValues()),
                    __('filament-query-builder::query-builder.operators.select.is.summary.values_glue.0'),
                    __('filament-query-builder::query-builder.operators.select.is.summary.values_glue.final'),
                ),
            ],
        );
    }

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

        return $this->matchAnswer($query, $qualifiedColumn, function (Builder $query) use ($qualifiedColumn, $values): void {
            foreach ($values as $value) {
                $query->orWhereJsonContains($qualifiedColumn, $value);
            }
        });
    }
}
