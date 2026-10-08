<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Enums\ConditionOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;

class NumberField extends InputField
{
    public function getInputType(): string
    {
        return 'number';
    }

    public function getConditionOperators(): array
    {
        return ConditionOperator::options(ConditionOperator::numeric());
    }

    public function getConditionValueComponent(): Field
    {
        return TextInput::make('value')->numeric();
    }

    public function getFilterConstraints(): array
    {
        return [AnswerConstraints::number($this->getKey(), $this->getColumnLabel())];
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
