<?php

use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Forms\ContactForm;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

it('belongs to a form', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => []]);

    expect($submission->form->id)->toBe($form->id);
});

it('is soft deleted and not returned in default queries', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => []]);
    $submission->delete();

    expect(FormSubmission::find($submission->id))->toBeNull()
        ->and(FormSubmission::withTrashed()->find($submission->id))->not->toBeNull();
});

it('getFormattedData returns scalar values unchanged', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['name' => 'Alice', 'message' => 'Hello'],
    ]);

    expect($submission->getFormattedData())->toBe(['name' => 'Alice', 'message' => 'Hello']);
});

it('getFormattedData flattens array values to a comma-separated string', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['interests' => ['php', 'laravel', 'filament']],
    ]);

    expect($submission->getFormattedData())->toBe(['interests' => 'php, laravel, filament']);
});

it('getFormattedData filters out empty/null values', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['name' => 'Alice', 'phone' => '', 'fax' => null],
    ]);

    expect($submission->getFormattedData())->toHaveKey('name')
        ->not->toHaveKey('phone')
        ->not->toHaveKey('fax');
});

class ShoutingContactForm extends ContactForm
{
    public function formatValues(array $values, FormSubmission $submission): array
    {
        return [...$values, 'name' => strtoupper($values['name'] ?? '')];
    }
}

it('shows a choice by its label everywhere an answer is shown', function () {
    $form = Form::create([
        'title' => 'Contact',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'radio', 'label' => 'Aanhef', 'key' => 'aanhef', 'options' => [
                ['value' => 'mw', 'label' => 'Mevrouw'],
            ]],
        ]],
    ]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['aanhef' => 'mw']]);

    expect($submission->getDisplayText('aanhef'))->toBe('Mevrouw')
        ->and($submission->getAnswers()['current'][0]->value)->toBe('Mevrouw')
        ->and(SubmissionPlaceholders::make($submission)->replace('{{ $aanhef }}'))->toBe('Mevrouw');
});

it('runs the form type formatting wherever an answer is shown', function () {
    config(['filament-form-builder.types.shouting' => ShoutingContactForm::class]);

    $form = Form::create(['title' => 'Contact', 'template' => 'shouting']);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['name' => 'jan']]);

    expect($submission->getDisplayText('name'))->toBe('JAN')
        ->and(SubmissionPlaceholders::make($submission)->replace('{{ $name }}'))->toBe('JAN');
});
