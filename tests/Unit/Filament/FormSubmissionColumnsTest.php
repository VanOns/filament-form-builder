<?php

use Filament\Tables\Columns\TextColumn;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\RadioField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TitleField;
use VanOns\FilamentFormBuilder\Filament\Tables\FormSubmissionColumns;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\View\Components\Forms\ContactForm;
use VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm;

class LabelledContactForm extends ContactForm
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Naam', 'message' => 'Bericht'];
    }
}

function customForm(array $fields): Form
{
    return Form::create([
        'title' => 'Solliciteren',
        'template' => CustomForm::class,
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
        ['fieldType' => TextInputField::class, 'label' => 'Voornaam'],
        ['fieldType' => TextInputField::class, 'label' => 'Achternaam'],
    ]);

    expect(columnMap($form))->toBe([
        'Created at' => 'created_at',
        'Submitter email' => 'submitter_email',
        'Voornaam' => 'data.key_voornaam',
        'Achternaam' => 'data.key_achternaam',
    ]);
});

it('leaves out fields that never reach the submission', function () {
    // A title is decoration on the form; a column for it would always be empty.
    $form = customForm([
        ['fieldType' => TitleField::class, 'label' => 'Jouw gegevens'],
        ['fieldType' => TextInputField::class, 'label' => 'Voornaam'],
    ]);

    expect(columnMap($form))->not->toHaveKey('Jouw gegevens')
        ->and(columnMap($form))->toHaveKey('Voornaam');
});

it('hides the field columns until someone asks for them', function () {
    $form = customForm([['fieldType' => TextInputField::class, 'label' => 'Voornaam']]);

    $columns = collect(FormSubmissionColumns::for($form)->columns())
        ->keyBy(fn (TextColumn $column): string => $column->getName());

    expect($columns->get('data.key_voornaam')->isToggledHiddenByDefault())->toBeTrue()
        ->and($columns->get('created_at')->isToggleable())->toBeFalse();
});

it('shortens a label that is a whole paragraph', function () {
    $consent = 'Ik ga ermee akkoord dat mijn gegevens via deze website tot 4 weken worden bewaard';
    $form = customForm([['fieldType' => TextInputField::class, 'label' => $consent, 'key' => 'akkoord']]);

    $label = collect(FormSubmissionColumns::for($form)->columns())
        ->first(fn (TextColumn $column): bool => $column->getName() === 'data.key_akkoord')
        ->getLabel();

    expect(strlen($label))->toBeLessThan(strlen($consent))
        ->and($label)->toEndWith('...');
});

it('takes the columns of a template form from its declared labels', function () {
    $form = Form::create(['title' => 'Contact', 'template' => LabelledContactForm::class]);

    expect(columnMap($form))->toHaveKeys(['Naam', 'Bericht']);
});

it('falls back to the rule keys of a template that declares none', function () {
    $form = Form::create(['title' => 'Contact', 'template' => ContactForm::class]);

    expect(columnMap($form))->toHaveKeys(['Name', 'Company Name', 'Phone Number', 'Message']);
});

it('offers a filter for a field with a fixed list of choices', function () {
    $form = customForm([
        ['fieldType' => TextInputField::class, 'label' => 'Voornaam'],
        ['fieldType' => RadioField::class, 'label' => 'Aanhef', 'options' => [
            ['value' => 'dhr', 'label' => 'Dhr.'],
            ['value' => 'mw', 'label' => 'Mw.'],
        ]],
    ]);

    $filters = FormSubmissionColumns::for($form)->filters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0]->getName())->toBe('key_aanhef')
        ->and($filters[0]->getOptions())->toBe(['dhr' => 'Dhr.', 'mw' => 'Mw.']);
});

it('offers no filter when there is nothing to choose from', function () {
    $form = customForm([['fieldType' => TextInputField::class, 'label' => 'Voornaam']]);

    expect(FormSubmissionColumns::for($form)->filters())->toBe([]);
});

it('shows the label of a chosen option, not the value that was stored', function () {
    $form = customForm([
        ['fieldType' => RadioField::class, 'label' => 'Aanhef', 'options' => [
            ['value' => 'dhr', 'label' => 'Dhr.'],
            ['value' => 'mw', 'label' => 'Mw.'],
        ]],
    ]);

    $column = collect(FormSubmissionColumns::for($form)->columns())
        ->first(fn (TextColumn $column): bool => $column->getName() === 'data.key_aanhef');

    expect($column->formatState('mw'))->toBe('Mw.')
        // A value the field no longer offers still has to show something.
        ->and($column->formatState('onbekend'))->toBe('onbekend');
});
