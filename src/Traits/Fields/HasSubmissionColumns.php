<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait HasSubmissionColumns
{
    /**
     * A field with a fixed list of choices answers here; free text does not,
     * and is searched instead.
     *
     * @return array<string, string> value => label
     */
    public function getFilterOptions(): array
    {
        return [];
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
}
