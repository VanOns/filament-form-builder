<?php

namespace Tests\Fixtures;

use Closure;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\EmailField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\StepField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SubmitField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Forms\FormType;

class SignUpForm extends FormType
{
    public function fields(): array
    {
        return [
            TextInputField::make('gebruikersnaam')
                ->label('Gebruikersnaam')
                ->required()
                ->rules([fn (string $attribute, mixed $value, Closure $fail) => $value === 'jan' ? $fail('Die naam is al bezet.') : null]),
            StepField::make('over_jou')->title('Over jou'),
            EmailField::make('email')->label('E-mailadres')->required(),
            SubmitField::make('verstuur'),
        ];
    }

    public function validatesSteps(): bool
    {
        return true;
    }
}
