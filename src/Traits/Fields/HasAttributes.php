<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait HasAttributes
{
    public function getAttributesList(): array
    {
        return array_filter([
            'data-visible-when-key' => $this->visibleWhenKey,
            'data-visible-when-value' => $this->visibleWhenValue,
        ]);
    }

    public function getAttributes(): string
    {
        $attributes = $this->getAttributesList();
        $extraAttributes = '';

        foreach ($attributes as $key => $value) {
            $extraAttributes .= "{$key}=\"{$value}\" ";
        }

        return trim($extraAttributes);
    }
}