<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;

trait HasColumns
{
    /**
     * The number of grid columns the form is rendered with. Override in a form
     * template to deviate from the configured default.
     */
    public static function columns(): int
    {
        return TemplateHelper::defaultColumns();
    }
}
