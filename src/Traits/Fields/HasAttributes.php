<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Illuminate\Support\HtmlString;
use VanOns\FilamentFormBuilder\Helpers\AttributeHelper;

trait HasAttributes
{
    /**
     * @return array<string, string|null>
     */
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

    /**
     * @param array<string, string|null>|null $attributes
     */
    public function getAttributes(?array $attributes = null, bool $withRequired = true): HtmlString
    {
        return AttributeHelper::render($attributes ?? $this->getAttributesList($withRequired));
    }

    public function getWrapperAttributes(): HtmlString
    {
        return $this->getAttributes([
            'data-form-builder-input-wrapper' => $this->getKey(),
        ]);
    }
}
