<?php

use Filament\Tables\Columns\TextColumn;
use VanOns\FilamentFormBuilder\Filament\Tables\FormSubmissionColumns;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

function customForm(array $fields): Form
{
    return Form::create([
        'title' => 'Solliciteren',
        'template' => 'custom',
        'custom' => ['fields' => $fields],
    ]);
}

/**
 * @return array<string, string> label => column name
 */
function columnMap(Form $form): array
{
    return collect(FormSubmissionColumns::for($form)->columns())
        ->mapWithKeys(fn (TextColumn $column): array => [$column->getLabel() => $column->getName()])
        ->all();
}

it('gives every input field of a custom form a column', function () {
    $form = customForm([
        ['type' => 'text', 'label' => 'Voornaam'],
        ['type' => 'text', 'label' => 'Achternaam'],
    ]);

    expect(columnMap($form))->toBe([
        'Created at' => 'created_at',
        'Submitter email' => 'submitter_email',
        'Voornaam' => 'data.voornaam',
        'Achternaam' => 'data.achternaam',
    ]);
});

it('leaves out fields that never reach the submission', function () {
    // A title is decoration on the form; a column for it would always be empty.
    $form = customForm([
        ['type' => 'title', 'label' => 'Jouw gegevens'],
        ['type' => 'text', 'label' => 'Voornaam'],
    ]);

    expect(columnMap($form))->not->toHaveKey('Jouw gegevens')
        ->and(columnMap($form))->toHaveKey('Voornaam');
});

it('hides the field columns until someone asks for them', function () {
    $form = customForm([['type' => 'text', 'label' => 'Voornaam']]);

    $columns = collect(FormSubmissionColumns::for($form)->columns())
        ->keyBy(fn (TextColumn $column): string => $column->getName());

    expect($columns->get('data.voornaam')->isToggledHiddenByDefault())->toBeTrue()
        ->and($columns->get('created_at')->isToggleable())->toBeFalse();
});

it('shortens a label that is a whole paragraph', function () {
    $consent = 'Ik ga ermee akkoord dat mijn gegevens via deze website tot 4 weken worden bewaard';
    $form = customForm([['type' => 'text', 'label' => $consent, 'key' => 'akkoord']]);

    $label = collect(FormSubmissionColumns::for($form)->columns())
        ->first(fn (TextColumn $column): bool => $column->getName() === 'data.akkoord')
        ->getLabel();

    expect(strlen($label))->toBeLessThan(strlen($consent))
        ->and($label)->toEndWith('...');
});

it('gives the fields a form type has in code a column too', function () {
    $form = Form::create(['title' => 'Contact', 'template' => 'contact']);

    expect(columnMap($form))->toHaveKeys(['Name', 'Company name', 'Email address', 'Phone number', 'Message']);
});

it('offers a filter for a field with a fixed list of choices', function () {
    $form = customForm([
        ['type' => 'text', 'label' => 'Voornaam'],
        ['type' => 'radio', 'label' => 'Aanhef', 'options' => [
            ['value' => 'dhr', 'label' => 'Dhr.'],
            ['value' => 'mw', 'label' => 'Mw.'],
        ]],
    ]);

    $filters = FormSubmissionColumns::for($form)->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0]->getName())->toBe('aanhef')
        ->and($filters[0]->getOptions())->toBe(['dhr' => 'Dhr.', 'mw' => 'Mw.']);
});

it('offers no filter when there is nothing to choose from', function () {
    $form = customForm([['type' => 'text', 'label' => 'Voornaam']]);

    expect(FormSubmissionColumns::for($form)->filters())->toBe([]);
});

it('shows the label of a chosen option, not the value that was stored', function () {
    $form = customForm([
        ['type' => 'radio', 'label' => 'Aanhef', 'options' => [
            ['value' => 'dhr', 'label' => 'Dhr.'],
            ['value' => 'mw', 'label' => 'Mw.'],
        ]],
    ]);

    $cell = fn (string $answer): ?string => FormSubmission::create(['form_id' => $form->id, 'data' => ['aanhef' => $answer]])
        ->getDisplayText('aanhef');

    expect($cell('mw'))->toBe('Mw.')
        // A value the field no longer offers still has to show something.
        ->and($cell('onbekend'))->toBe('onbekend');
});
