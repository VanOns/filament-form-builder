<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use VanOns\FilamentFormBuilder\Traits\Fields\HasLength;

class TextInputField extends InputField
{
    use HasLength;

    public function getInputType(): string
    {
        return 'text';
    }

    protected static function lengthLimitKey(): string
    {
        return 'text';
    }
}
