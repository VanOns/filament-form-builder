<?php

namespace VanOns\FilamentFormBuilder\Helpers;

use Filament\Forms\Components\Component;

class FieldHelper
{
    /**
     * @param string|null $field
     * @return array<Component>
     */
    public static function getFields(?string $field): array
    {
        if (is_null($field) || !class_exists($field) || !method_exists($field, 'getFields')) {
            return [];
        }

        return $field::getFields();
    }
}