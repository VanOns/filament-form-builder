<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;
use VanOns\FilamentFormBuilder\Forms;

return [
    'add_nav_group' => true,
    'types' => [
        'custom' => Forms\CustomForm::class,
        'contact' => Forms\ContactForm::class,
    ],
    'integrations' => [
        // Insert integrations here
    ],
    'rate-limit-hour' => 60,
    'email_notification_enabled' => true,
    'submit_notification_query_enabled' => true,
    'form-middleware' => ['web'],
    'form-uploads-middleware' => [],
    'form-uploads-disk' => 'local',
    'form-uploads-max-size' => 10240,
    'form-uploads-link-days' => 7,
    // How freely fields sit side by side: 'flexible' (quarters, thirds and
    // halves), 'two_columns' (halves only) or 'full_width' (one field a row).
    'layout' => 'flexible',
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
    'field_conditions' => true,
];
