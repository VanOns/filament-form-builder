<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

trait CanBeCustom
{
    /**
     * Determines if the form component has a custom form builder.
     *
     * @return bool
     */
    public static function isCustom(): bool
    {
        return false;
    }
}
