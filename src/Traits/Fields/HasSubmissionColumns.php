<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\QueryBuilder\Constraints\Constraint;
use VanOns\FilamentFormBuilder\Enums\ConditionOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;

trait HasSubmissionColumns
{
    /**
     * A field with a fixed list of choices answers here; free text does not.
     *
     * @return array<string, string> value => label
     */
    public function getFilterOptions(): array
    {
        return [];
    }

    /**
     * How a condition can test this field's answer, as operator => label.
     *
     * @return array<string, string>
     */
    public function getConditionOperators(): array
    {
        return ConditionOperator::options(ConditionOperator::basic());
    }

    /**
     * The rules the submissions table can filter this field's answers by, one
     * per column it fills. The table gives them the field type's icon.
     *
     * @return array<Constraint>
     */
    public function getFilterConstraints(): array
    {
        $constraints = [];

        foreach ($this->getSubmissionColumns() as $key => $label) {
            $constraints[] = AnswerConstraints::text($key, $label);
        }

        return $constraints;
    }

    /**
     * A field that stores something other than what the visitor saw answers here.
     */
    public function formatSubmissionValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Most fields fill one key. A field that writes several values answers with
     * all of them, so each gets a column it can be sorted on.
     *
     * @return array<string, string> key => label
     */
    public function getSubmissionColumns(): array
    {
        return [$this->getKey() => $this->getLabel()];
    }

    /**
     * The columns that hold an e-mail address, which a notification can be
     * sent to.
     *
     * @return array<string, string> key => label
     */
    public function getEmailColumns(): array
    {
        return [];
    }

    /**
     * The keys a visitor posts for this field. Any other column is filled in by
     * the field or its form type, so it is never taken from the request.
     *
     * @return array<int, string>
     */
    public function getInputKeys(): array
    {
        return [$this->getKey()];
    }
}
