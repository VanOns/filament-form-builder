<?php

use Illuminate\Support\Str;
use Tests\Fixtures\ApplicationForm;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Models\Form;

function steppedForm(array $steps = [], array $fields = []): Form
{
    return Form::create(['title' => 'Aanvraag ' . Str::random(6), 'template' => 'custom', 'custom' => [
        'fields' => $fields ?: [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam', 'required' => true],
            ['type' => 'step', 'title' => 'Je aanvraag'],
            ['type' => 'textarea', 'label' => 'Bericht', 'key' => 'bericht'],
            ['type' => 'turnstile', 'key' => 'cf-turnstile-response'],
            ['type' => 'step', 'title' => 'Afronden'],
            ['type' => 'consent', 'label' => 'Privacy', 'key' => 'privacy'],
            ['type' => 'submit', 'label' => 'Versturen'],
        ],
        'steps' => $steps,
    ]]);
}

function stepTitles(Form $form): array
{
    return array_column($form->getSteps(), 'title');
}

it('starts a step at every step field, the first under the title of the start block', function () {
    $form = steppedForm(['title' => 'Over jou']);

    expect($form->hasSteps())->toBeTrue()
        ->and(stepTitles($form))->toBe(['Over jou', 'Je aanvraag', 'Afronden'])
        ->and(array_map(fn (array $step): array => array_map(fn (FormField $field): string => $field->getKey(), $step['fields']), $form->getSteps()))
        ->toBe([['naam'], ['bericht'], ['privacy']]);
});

it('keeps the submit button and a captcha for after the last step', function () {
    $form = steppedForm();

    expect(array_map(fn (FormField $field): string => class_basename($field), $form->getEndFields()))->toBe(['TurnstileField', 'SubmitField']);
});

it('leaves out a step without fields', function () {
    $form = steppedForm(fields: [
        ['type' => 'step', 'title' => 'Eerst'],
        ['type' => 'step', 'title' => 'Toch dit'],
        ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
    ]);

    expect(stepTitles($form))->toBe(['Toch dit']);
});

it('gives a front end of its own the keys of every step', function () {
    expect(steppedForm(['title' => 'Over jou'])->getStepKeys())->toBe([
        ['title' => 'Over jou', 'keys' => ['naam']],
        ['title' => 'Je aanvraag', 'keys' => ['bericht']],
        ['title' => 'Afronden', 'keys' => ['privacy']],
    ]);
});

it('splits the fields of a form type in code into steps as well', function () {
    config(['filament-form-builder.types.application' => ApplicationForm::class]);

    $form = Form::create(['title' => 'Sollicitatie', 'template' => 'application', 'custom' => ['fields' => [
        ['type' => 'step', 'title' => 'Motivatie'],
        ['type' => 'textarea', 'label' => 'Motivatie', 'key' => 'motivatie'],
    ]]]);

    expect(stepTitles($form))->toBe([null, 'Motivatie'])
        ->and(array_map(fn (array $step): int => count($step['fields']), $form->getSteps()))->toBe([2, 2]);
});

it('groups the answers per step on the detail page', function () {
    $snapshot = steppedForm(['title' => 'Over jou'])->getFieldSnapshot();

    expect(array_map(fn (array $field): ?string => $field['title'], $snapshot))
        ->toBe(['naam' => 'Over jou', 'bericht' => 'Je aanvraag', 'privacy' => 'Afronden']);
});
