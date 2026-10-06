<?php

use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

function submit(Form $form, array $payload): FormSubmission
{
    test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), $payload);

    return FormSubmission::query()->where('form_id', $form->id)->sole();
}

it('stores only the fields a custom form asks for', function () {
    $form = Form::create([
        'title' => 'Terugbellen',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'title', 'title' => 'Bel me terug'],
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
        ]],
    ]);

    $submission = submit($form, [
        'naam' => 'Jan',
        'title_field' => 'not a field',
        'is_admin' => '1',
        'submitter_email' => 'jan@example.com',
    ]);

    expect($submission->data)->toBe(['naam' => 'Jan'])
        ->and($submission->submitter_email)->toBeNull();
});

it('stores only the fields a form type has in code', function () {
    $form = Form::create(['title' => 'Contact', 'template' => 'contact']);

    $submission = submit($form, [
        'name' => 'Jan',
        'company_name' => 'Van Ons',
        'email' => 'jan@example.com',
        'phone_number' => '0612345678',
        'message' => 'Hallo',
        'is_admin' => '1',
    ]);

    expect($submission->data)->toBe([
        'name' => 'Jan',
        'company_name' => 'Van Ons',
        'email' => 'jan@example.com',
        'phone_number' => '0612345678',
        'message' => 'Hallo',
    ])->and($submission->submitter_email)->toBe('jan@example.com');
});

it('takes the submitter from the first e-mail field that was filled in', function () {
    $form = Form::create([
        'title' => 'Offerte',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'email', 'label' => 'Werkmail', 'key' => 'werkmail'],
            ['type' => 'email', 'label' => 'E-mailadres', 'key' => 'e-mailadres'],
        ]],
    ]);

    expect(submit($form, ['e-mailadres' => 'jan@example.com'])->submitter_email)->toBe('jan@example.com');
});

it('accepts a form whose optional fields were left empty', function () {
    $form = Form::create([
        'title' => 'Terugbellen',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'email', 'label' => 'E-mail', 'key' => 'email'],
            ['type' => 'dropdown', 'label' => 'Land', 'key' => 'land', 'options' => [['value' => 'nl', 'label' => 'Nederland']]],
        ]],
    ]);

    expect(submit($form, ['email' => '', 'land' => ''])->data)->toBe(['email' => null, 'land' => null]);
});
