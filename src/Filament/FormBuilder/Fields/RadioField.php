<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class RadioField extends ChoiceField
{
    public static function allowsMultiple(): bool
    {
        return false;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedListBullet;
    }
}
