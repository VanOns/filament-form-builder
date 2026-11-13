<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

trait CanBeHidden
{
    public ?bool $hidden = false;

    public function hidden(bool $condition = true): static
    {
        $this->hidden = $condition;

        return $this;
    }

    public function isHidden(): bool
    {
        return static::canBeHidden() && (bool) $this->hidden;
    }

    public static function canBeHidden(): bool
    {
        return static::isInput();
    }
}
