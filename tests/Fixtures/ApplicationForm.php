<?php

namespace Tests\Fixtures;

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SubmitField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Forms\CustomFields;
use VanOns\FilamentFormBuilder\Forms\FormType;

class ApplicationForm extends FormType
{
    public function fields(): array
    {
        return [
            TextInputField::make('naam')->label('Naam')->required(),
            TextInputField::make('vacature')->label('Vacature')->hidden()->default('Adviseur'),
            CustomFields::make(),
            CheckboxField::make('privacy')->label('Privacy')->required(),
            SubmitField::make('verstuur'),
        ];
    }

    public function extraValues(): array
    {
        return ['ontvangen_via' => 'Ontvangen via'];
    }

    public function beforeStore(array $data): array
    {
        return [...$data, 'ontvangen_via' => 'website'];
    }
}
