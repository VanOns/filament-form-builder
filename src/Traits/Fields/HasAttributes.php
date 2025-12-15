<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use VanOns\FilamentFormBuilder\Helpers\AttributeHelper;

trait HasAttributes
{
    public function getAttributesList(bool $withRequired = true): array
    {
        return array_filter([
            'data-form-builder-input' => $this->getKey(),
            'data-visible-when-key' => $this->getVisibleWenKey(),
            'data-visible-when-value' => $this->getVisibleWhenValue(),
            'data-required' => !$withRequired
                ? null
                : ($this->isRequired() ? 'true' : 'false'),
        ]);
    }

    public function getAttributes(array $attributes = null, bool $withRequired = true): string
    {
        return AttributeHelper::arrayToString($attributes ?? $this->getAttributesList($withRequired));
    }

    public function getWrapperAttributes(): string
    {
        return $this->getAttributes([
            'data-form-builder-input-wrapper' => $this->getKey(),
        ]);
    }
}