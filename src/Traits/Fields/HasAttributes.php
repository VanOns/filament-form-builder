<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use VanOns\FilamentFormBuilder\Helpers\AttributeHelper;

trait HasAttributes
{
    public function getAttributesList(): array
    {
        return array_filter([
            'data-form-builder-input' => $this->getKey(),
            'data-visible-when-key' => $this->visibleWhenKey,
            'data-visible-when-value' => $this->getVisibleWhenValue(),
        ]);
    }

    public function getAttributes(array $attributes = null): string
    {
        return AttributeHelper::arrayToString($attributes ?? $this->getAttributesList());
    }

    public function getWrapperAttributes(): string
    {
        return $this->getAttributes([
            'data-form-builder-input-wrapper' => $this->getKey(),
        ]);
    }
}