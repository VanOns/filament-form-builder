<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

return [
    'add_nav_group' => true,
    'templates' => [
        // App\View\Components\MyForm::class => 'My Form'
    ],
    'rate-limit_hour' => 6,
    'email_notification_enabled' => true,
    'form-middleware' => ['web'],
    'fields' => [
        Fields\TextField::class,
        Fields\CheckboxField::class,
        Fields\SelectField::class,
        Fields\SubmitField::class,
        Fields\EmailField::class,
    ],
];
