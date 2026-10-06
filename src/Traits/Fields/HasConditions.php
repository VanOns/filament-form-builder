<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use VanOns\FilamentFormBuilder\Classes\FieldConditions;

trait HasConditions
{
    /**
     * @var array<int|string, array{key?: string, operator?: string, value?: string|null}>
     */
    public array $conditions = [];

    public ?string $conditionMatch = 'all';

    /**
     * @param  array<int, array{key: string, operator: string, value?: string|null}>  $conditions
     */
    public function conditions(array $conditions, string $match = 'all'): static
    {
        $this->conditions = $conditions;
        $this->conditionMatch = $match;

        return $this;
    }

    public function getConditions(): FieldConditions
    {
        return FieldConditions::fromArray($this->conditions, $this->conditionMatch);
    }

    public function hasConditions(): bool
    {
        return !$this->getConditions()->isEmpty();
    }

    public static function hasConditionSettings(): bool
    {
        return config('filament-form-builder.field_conditions') === true;
    }
}
