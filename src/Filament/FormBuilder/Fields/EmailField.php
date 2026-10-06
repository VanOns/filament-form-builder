<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class EmailField extends InputField
{
    public static string $answerView = 'filament-form-builder::answers.email';

    public function getInputType(): string
    {
        return 'email';
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedEnvelope;
    }

    protected function getTypeRules(): array
    {
        return ['email'];
    }
}
