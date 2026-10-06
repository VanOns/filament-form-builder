<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class BranchField extends TextInputField
{
    public function getSubmissionColumns(): array
    {
        return [$this->getKey() => 'Vestiging', 'branch_email' => 'Vestiging e-mailadres'];
    }
}

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
    ]);

    expect($submission->data)->toBe(['naam' => 'Jan']);
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
    ]);
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

it('never takes a column a field fills in itself from the visitor', function () {
    config(['filament-form-builder.fields.branch' => BranchField::class]);

    $form = Form::create([
        'title' => 'Afspraak',
        'template' => 'custom',
        'custom' => ['fields' => [['type' => 'branch', 'label' => 'Vestiging', 'key' => 'vestiging']]],
    ]);

    expect(submit($form, ['vestiging' => '12', 'branch_email' => 'iemand@example.test'])->data)->toBe(['vestiging' => '12'])
        ->and($form->getSubmissionFields())->toHaveKey('branch_email');
});
