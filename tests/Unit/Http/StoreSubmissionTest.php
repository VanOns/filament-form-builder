<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\EmailField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TitleField;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\View\Components\Forms\ContactForm;
use VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm;

function submit(Form $form, array $payload): FormSubmission
{
    test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), $payload);

    return FormSubmission::query()->where('form_id', $form->id)->sole();
}

it('stores only the fields a custom form asks for', function () {
    $form = Form::create([
        'title' => 'Terugbellen',
        'template' => CustomForm::class,
        'custom' => ['fields' => [
            ['fieldType' => TitleField::class, 'title' => 'Bel me terug'],
            ['fieldType' => TextInputField::class, 'label' => 'Naam', 'key' => 'naam'],
        ]],
    ]);

    $submission = submit($form, [
        'naam' => 'Jan',
        'title_field' => 'not a field',
        'is_admin' => '1',
        'submitter_email' => 'jan@example.com',
    ]);

    expect($submission->data)->toBe(['naam' => 'Jan'])
        ->and($submission->submitter_email)->toBe('jan@example.com');
});

it('stores only the fields a template validates or labels', function () {
    $form = Form::create(['title' => 'Contact', 'template' => ContactForm::class]);

    $submission = submit($form, [
        'name' => 'Jan',
        'company_name' => 'Van Ons',
        'submitter_email' => 'jan@example.com',
        'phone_number' => '0612345678',
        'message' => 'Hallo',
        'is_admin' => '1',
    ]);

    expect($submission->data)->toBe([
        'name' => 'Jan',
        'company_name' => 'Van Ons',
        'phone_number' => '0612345678',
        'message' => 'Hallo',
    ]);
});

it('takes the submitter from the first e-mail field that was filled in', function () {
    $form = Form::create([
        'title' => 'Offerte',
        'template' => CustomForm::class,
        'custom' => ['fields' => [
            ['fieldType' => EmailField::class, 'label' => 'Werkmail', 'key' => 'werkmail'],
            ['fieldType' => EmailField::class, 'label' => 'E-mailadres', 'key' => 'e-mailadres'],
        ]],
    ]);

    expect(submit($form, ['e-mailadres' => 'jan@example.com'])->submitter_email)->toBe('jan@example.com');
});

it('prefers a submitter e-mail that was posted as such', function () {
    $form = Form::create([
        'title' => 'Offerte',
        'template' => CustomForm::class,
        'custom' => ['fields' => [['fieldType' => EmailField::class, 'label' => 'E-mailadres', 'key' => 'e-mailadres']]],
    ]);

    expect(submit($form, ['e-mailadres' => 'jan@example.com', 'submitter_email' => 'piet@example.com'])->submitter_email)
        ->toBe('piet@example.com');
});
