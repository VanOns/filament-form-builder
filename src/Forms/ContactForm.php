<?php

namespace VanOns\FilamentFormBuilder\Forms;

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\EmailField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\PhoneField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SubmitField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;

class ContactForm extends FormType
{
    public static function getLabel(): string
    {
        return __('filament-form-builder::general.types.contact');
    }

    public function fields(): array
    {
        return [
            TextInputField::make('name')->label(__('filament-form-builder::fields.contact.name'))->required()->rules(['max:255'])->span(1),
            TextInputField::make('company_name')->label(__('filament-form-builder::fields.contact.company_name'))->rules(['max:255'])->span(1),
            EmailField::make('email')->label(__('filament-form-builder::fields.contact.email'))->required()->rules(['max:255'])->span(1),
            PhoneField::make('phone_number')->label(__('filament-form-builder::fields.contact.phone_number'))->span(1),
            TextAreaField::make('message')->label(__('filament-form-builder::fields.contact.message'))->required()->rules(['max:6000'])->span(2),
            SubmitField::make('submit')->label(__('filament-form-builder::fields.contact.submit'))->span(2),
        ];
    }
}
