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
            'data-conditions' => $this->hasConditions() ? json_encode($this->getConditions()->toArray()) : null,
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
}
