<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class NumberField extends InputField
{
    public function getInputType(): string
    {
        return 'number';
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedHashtag;
    }

    protected function getTypeRules(): array
    {
        return ['numeric'];
    }
}
