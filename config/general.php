<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

return [
    'add_nav_group' => true,
    'templates' => [
        \VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm::class => 'Custom',
        \VanOns\FilamentFormBuilder\View\Components\Forms\ContactForm::class => 'Contact',
    ],
    'integrations' => [
        // Insert integrations here
    ],
    'columns' => 2,
    'rate-limit-hour' => 60,
    'email_notification_enabled' => true,
    'submit_notification_query_enabled' => true,
    'form-middleware' => ['web'],
    'form-uploads-middleware' => [],
    'form-uploads-disk' => 'local',
    'form-uploads-max-size' => 10240,
    'form-uploads-link-days' => 7,
    'fields' => [
        'title' => Fields\TitleField::class,
        'text_block' => Fields\TextField::class,
        'text' => Fields\TextInputField::class,
        'textarea' => Fields\TextAreaField::class,
        'email' => Fields\EmailField::class,
        'phone' => Fields\PhoneField::class,
        'number' => Fields\NumberField::class,
        'radio' => Fields\RadioField::class,
        'checkbox_list' => Fields\CheckboxListField::class,
        'dropdown' => Fields\DropdownField::class,
        'checkbox' => Fields\CheckboxField::class,
        'file_upload' => Fields\FileUploadField::class,
        'recaptcha' => Fields\RecaptchaField::class,
        'submit' => Fields\SubmitField::class,
    ],
    'recaptcha' => [
        'enabled' => env('RECAPTCHA_ENABLED', false),
        'secret' => env('RECAPTCHA_SECRET', ''),
        'key' => env('RECAPTCHA_KEY', ''),
    ],
    'enable_export_action' => false,
    'field_visibility_settings' => true,
];
