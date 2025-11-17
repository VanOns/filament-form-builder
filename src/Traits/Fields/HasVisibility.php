<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait HasVisibility
{
    public ?string $visibleWhenKey = null;
    protected ?string $visibleWhenType = null;
    public ?string $visibleWhenValue = null;

    public function getVisibleWhenValue(): ?string
    {
        return match ($this->visibleWhenType) {
            'empty' => '__empty__',
            'not_empty' => '__not-empty__',
            'not_equals' => '__not__'  . $this->visibleWhenValue,
            default => $this->visibleWhenValue,
        };
    }
}