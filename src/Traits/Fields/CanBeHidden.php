<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait CanBeHidden
{
    public ?bool $hidden = false;

    public function isHidden(): bool
    {
        return static::canBeHidden() && (bool) $this->hidden;
    }

    public static function canBeHidden(): bool
    {
        return static::isInput();
    }
}
