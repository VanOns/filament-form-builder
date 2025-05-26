<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

return [
    'add_nav_group' => true,
    'templates' => [
        'custom' => __('filament-form-builder::fields.custom_form_builder'),
        // App\View\Components\MyForm::class => 'My Form'
    ],
    'rate-limit_hour' => 6,
    'email_notification_enabled' => true,
    'form-middleware' => ['web'],
    'fields' => [
        Fields\InputField::class,
        Fields\CheckboxField::class,
        Fields\SelectField::class,
        Fields\SubmitField::class,
        Fields\TextAreaField::class,
    ],
];
