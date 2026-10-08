<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\Fixtures\ApplicationForm;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Models\Form;

beforeEach(fn () => view()->share('errors', new ViewErrorBag()));

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

it('renders the steps as fieldsets, with the buttons between them hidden until the script shows them', function () {
    $html = Blade::render('<x-render-form :form="$form" />', ['form' => steppedForm(['title' => 'Over jou', 'next' => 'Verder'])]);

    expect($html)
        ->toContain('data-form-builder-steps')
        ->toContain('<legend class="ffb-step-title" tabindex="-1">Je aanvraag</legend>')
        ->toContain('class="ffb-progress ffb-progress-steps" data-form-builder-progress hidden')
        ->toContain('data-form-builder-next hidden')
        ->toContain('Verder')
        ->toContain('data-step-of="Step :current of :total"')
        ->and(substr_count($html, 'data-form-builder-step="'))->toBe(3)
        ->and(strpos($html, 'class="ffb-submit"'))->toBeGreaterThan(strrpos($html, '</fieldset>'));
});

it('shows a bar or no progress at all, as the start block sets', function () {
    $render = fn (string $progress): string => Blade::render('<x-render-form :form="$form" />', ['form' => steppedForm(['progress' => $progress])]);

    expect($render('bar'))->toContain('role="progressbar"')->not->toContain('ffb-progress-steps')
        ->and($render('none'))->not->toContain('data-form-builder-progress ');
});

it('renders a form without step fields as it always did', function () {
    $html = Blade::render('<x-render-form :form="$form" />', ['form' => steppedForm(fields: [
        ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
        ['type' => 'submit', 'label' => 'Versturen'],
    ])]);

    expect($html)->not->toContain('data-form-builder-steps')->not->toContain('<fieldset')
        ->toContain('class="ffb-submit"');
});

it('opens again on the step with an error, which the script finds by the error in it', function () {
    view()->share('errors', (new ViewErrorBag())->put('default', new MessageBag(['bericht' => 'Vul een bericht in.'])));

    $html = Blade::render('<x-render-form :form="$form" />', ['form' => steppedForm()]);

    expect($html)->toMatch('/data-form-builder-input-wrapper="bericht"[^>]*>.*?class="ffb-error"/s');
});
