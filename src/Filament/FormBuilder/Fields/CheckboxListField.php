<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class CheckboxListField extends ChoiceField
{
    public static function allowsMultiple(): bool
    {
        return true;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedQueueList;
    }
}
