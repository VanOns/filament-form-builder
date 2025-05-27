<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

return [
    'add_nav_group' => true,
    'templates' => [
        'custom' => 'Custom',
        \VanOns\FilamentFormBuilder\View\Components\Forms\ContactForm::class => 'Contact',
    ],
    'rate-limit_hour' => 6,
    'form-middleware' => ['web'],
    'fields' => [
        Fields\InputField::class,
        Fields\CheckboxField::class,
        Fields\SelectField::class,
        Fields\SubmitField::class,
        Fields\TextAreaField::class,
    ],
];
