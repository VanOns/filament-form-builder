<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait HasVisibility
{
    public ?string $visibleWhenKey = null;
    public mixed $visibleWhenValue = null;
}