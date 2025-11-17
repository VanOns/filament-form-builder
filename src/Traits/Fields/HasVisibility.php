<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use VanOns\FilamentFormBuilder\Enums\VisibilityType;

trait HasVisibility
{
    public ?string $visibleWhenKey = null;
    protected ?string $visibleWhenType = null;
    public ?string $visibleWhenValue = null;

    public function getVisibilityType(): ?VisibilityType
    {
        return VisibilityType::tryFrom($this->visibleWhenType);
    }

    public function getVisibleWhenValue(): ?string
    {
        return $this->getVisibilityType()?->formatString(
            $this->visibleWhenValue
        );
    }

    public function getRequiredVisibilityRule(): ?string
    {
        return $this->getVisibilityType()?->getRequiredRule(
            $this->visibleWhenKey,
            $this->getVisibleWhenValue()
        );
    }

    public function hasVisibilityCondition(): bool
    {
        return !empty($this->visibleWhenKey) || !empty($this->visibleWhenValue);
    }
}