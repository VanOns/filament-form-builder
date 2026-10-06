<?php

namespace Tests\Fixtures;

use VanOns\FilamentFormBuilder\Enums\FieldWidth;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SubmitField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Forms\CustomFields;
use VanOns\FilamentFormBuilder\Forms\FormType;

class HalfRowForm extends FormType
{
    public function fields(): array
    {
        return [
            TextInputField::make('naam')->label('Naam')->span(FieldWidth::HALF),
            CustomFields::make(),
            SubmitField::make('verstuur'),
        ];
    }
}
