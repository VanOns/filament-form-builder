<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PhoneField extends InputField
{
    public function getInputType(): string
    {
        return 'tel';
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedPhone;
    }

    protected function getTypeRules(): array
    {
        return ['regex:/^\+?[0-9\s\-().]{6,20}$/'];
    }
}
