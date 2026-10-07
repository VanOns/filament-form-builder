<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;
use VanOns\FilamentFormBuilder\Forms;

return [
    'types' => [
        'custom' => Forms\CustomForm::class,
        'contact' => Forms\ContactForm::class,
    ],
    'fields' => [
        'title' => Fields\TitleField::class,
        'text_block' => Fields\TextField::class,
        'text' => Fields\TextInputField::class,
        'textarea' => Fields\TextAreaField::class,
        'email' => Fields\EmailField::class,
        'phone' => Fields\PhoneField::class,
        'number' => Fields\NumberField::class,
        'date' => Fields\DateField::class,
        'radio' => Fields\RadioField::class,
        'checkbox_list' => Fields\CheckboxListField::class,
        'dropdown' => Fields\DropdownField::class,
        'checkbox' => Fields\CheckboxField::class,
        'consent' => Fields\ConsentField::class,
        'file_upload' => Fields\FileUploadField::class,
        'recaptcha' => Fields\RecaptchaField::class,
        'turnstile' => Fields\TurnstileField::class,
        'submit' => Fields\SubmitField::class,
    ],
    'integrations' => [
        // Insert integrations here
    ],
    // How freely fields sit side by side: 'flexible' (quarters, thirds and
    // halves), 'two_columns' (halves only) or 'full_width' (one field a row).
    'layout' => 'flexible',
    'field_conditions' => true,
    'email_notifications' => true,
    'redirect_query' => true,
    // The forms on the site load a minimal stylesheet with the grid; turn it off
    // to style them entirely yourself.
    'styles' => true,
    'form_middleware' => ['web'],
    'rate_limit_per_hour' => 60,
    // What a submission keeps about where it came from, shown with its details.
    // An IP address is personal data: false, 'anonymized' or 'full'.
    'submission_meta' => [
        'user_agent' => true,
        'locale' => true,
        'user' => true,
        'campaign' => true,
        'ip' => false,
    ],
    // Months a submission is kept, with its files, unless its form says otherwise;
    // null keeps them. Deleted each night, so the scheduler has to run.
    'retention_months' => null,
    'uploads' => [
        'disk' => 'local',
        // In kilobytes, per file.
        'max_size' => 10240,
        'link_days' => 7,
        // Uploads a notification attaches, in kilobytes together; the rest stay a link.
        'attach_max_size' => 10240,
        'middleware' => [],
    ],
    // A field nobody sees, which bots fill in, and the least time a person
    // takes to send a form. A front end of its own has to set both.
    'honeypot' => [
        'enabled' => true,
        'field' => 'ffb_website',
        'min_seconds' => 2,
    ],
    // The same answers from the same visitor within these seconds are stored once.
    'duplicate_seconds' => 10,
    'recaptcha' => [
        'enabled' => env('RECAPTCHA_ENABLED', false),
        'secret' => env('RECAPTCHA_SECRET', ''),
        'key' => env('RECAPTCHA_KEY', ''),
    ],
    'turnstile' => [
        'enabled' => env('TURNSTILE_ENABLED', false),
        'secret' => env('TURNSTILE_SECRET', ''),
        'key' => env('TURNSTILE_KEY', ''),
    ],
    // Defaults for every panel; a panel sets its own on the plugin.
    'navigation_group' => true,
    // Field types left out of the builder's palette, by their name in 'fields'.
    'without_fields' => [],
    // A queued Filament export: it needs the exports, job_batches and notifications
    // tables, see the installation docs.
    'export_action' => true,
];
