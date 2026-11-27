<?php

namespace VanOns\FilamentFormBuilder\Forms;

class CustomForm extends FormType
{
    public static function getLabel(): string
    {
        return __('filament-form-builder::general.types.custom');
    }

    public function fields(): array
    {
        return [CustomFields::make()];
    }
}
