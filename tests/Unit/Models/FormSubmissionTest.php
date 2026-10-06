<?php

use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\RadioField;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\View\Components\Forms\ContactForm;
use VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm;

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
    public static function modifyDataValues(array $data, FormSubmission $submission): array
    {
        return [...$data, 'name' => strtoupper($data['name'] ?? '')];
    }
}

it('shows a choice by its label everywhere an answer is shown', function () {
    $form = Form::create([
        'title' => 'Contact',
        'template' => CustomForm::class,
        'custom' => ['fields' => [
            ['fieldType' => RadioField::class, 'label' => 'Aanhef', 'key' => 'aanhef', 'options' => [
                ['value' => 'mw', 'label' => 'Mevrouw'],
            ]],
        ]],
    ]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['aanhef' => 'mw']]);

    expect($submission->getDisplayText('aanhef'))->toBe('Mevrouw')
        ->and($submission->getDetailData())->toBe(['Aanhef' => 'Mevrouw'])
        ->and(SubmissionPlaceholders::make($submission)->replace('{{ $aanhef }}'))->toBe('Mevrouw');
});

it('runs the template formatting wherever an answer is shown', function () {
    $form = Form::create(['title' => 'Contact', 'template' => ShoutingContactForm::class]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['name' => 'jan']]);

    expect($submission->getDisplayText('name'))->toBe('JAN')
        ->and(SubmissionPlaceholders::make($submission)->replace('{{ $name }}'))->toBe('JAN');
});
