<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

return [
    'add_nav_group' => true,
    'templates' => [
        \VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm::class => 'Custom',
        \VanOns\FilamentFormBuilder\View\Components\Forms\ContactForm::class => 'Contact',
    ],
    'rate-limit-hour' => 60,
    'email_notification_enabled' => false,
    'form-middleware' => ['web'],
    'form-uploads-middleware' => ['web', 'auth'],
    'form-uploads-disk' => 'private',
    'form-uploads-max-size' => 100,
    'fields' => [
        Fields\TitleField::class,
        Fields\InputField::class,
        Fields\TextAreaField::class,
        Fields\SelectField::class,
        Fields\CheckboxField::class,
        Fields\FileUploadField::class,
        Fields\RecaptchaField::class,
        Fields\SubmitField::class,
    ],
    'recaptcha' => [
        'enabled' => env('RECAPTCHA_ENABLED', false),
        'secret' => env('RECAPTCHA_SECRET', ''),
        'key' => env('RECAPTCHA_KEY', ''),
    ],
];
