<?php

use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\RelationManagers\FormSubmissionsRelationManager;
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
        'Voornaam' => 'data.voornaam',
        'Achternaam' => 'data.achternaam',
        'Submitted from' => 'source_url',
        'Browser' => 'meta.user_agent',
        'Language' => 'meta.locale',
        'Signed in as' => 'meta.user',
        'Campaign' => 'meta.campaign',
    ]);
});

it('offers where a submission came from as columns, as far as the config collects it', function () {
    $form = customForm([['type' => 'text', 'label' => 'Voornaam']]);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'source_url' => 'https://www.fonk.nl/vacatures/adviseur',
        'meta' => [
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
            'campaign' => ['source' => 'nieuwsbrief', 'campaign' => 'najaar'],
            'ip' => '203.0.113.0',
        ],
    ]);

    $columns = collect(FormSubmissionColumns::for($form)->columns())
        ->keyBy(fn (TextColumn $column): string => $column->getName());

    // An IP address is only kept when the config asks for it, so neither is its column.
    expect($columns)->not->toHaveKey('meta.ip')
        ->and($columns->get('source_url')->isToggledHiddenByDefault())->toBeTrue()
        ->and($columns->get('meta.campaign')->isToggledHiddenByDefault())->toBeTrue();

    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    Livewire::test(FormSubmissionsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class])
        ->assertTableColumnFormattedStateSet('source_url', 'fonk.nl/vacatures/adviseur', $submission)
        ->assertTableColumnStateSet('meta.user_agent', 'Chrome · macOS', $submission)
        ->assertTableColumnStateSet('meta.campaign', 'source: nieuwsbrief, campaign: najaar', $submission);

    config(['filament-form-builder.submission_meta.ip' => 'anonymized']);

    expect(columnMap($form))->toHaveKey('IP address', 'meta.ip');
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

it('shows the columns of the fields that ask for it, under their short name', function () {
    $form = customForm([
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam', 'showColumn' => true],
        ['type' => 'number', 'label' => 'Hoeveel uur per week wil je werken?', 'key' => 'uren', 'columnLabel' => 'Uren', 'showColumn' => true],
        ['type' => 'text', 'label' => 'Opmerking', 'key' => 'opmerking'],
    ]);

    $columns = collect(FormSubmissionColumns::for($form)->columns())
        ->keyBy(fn (TextColumn $column): string => $column->getName());

    expect($columns->get('data.voornaam')->isToggledHiddenByDefault())->toBeFalse()
        ->and($columns->get('data.uren')->getLabel())->toBe('Uren')
        ->and($columns->get('data.uren')->isToggledHiddenByDefault())->toBeFalse()
        ->and($columns->get('data.opmerking')->isToggledHiddenByDefault())->toBeTrue()
        ->and($form->getSubmissionFields())->toMatchArray(['uren' => 'Uren'])
        ->and($form->getMergeTagGroups()[0]['tags']['uren']['label'])->toBe('Uren');

    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['uren' => '32']]);

    expect($submission->getAnswers()['current'][0]->label)->toBe('Hoeveel uur per week wil je werken?')
        ->and($submission->getFormattedData(formatKeys: true))->toBe(['Uren' => '32']);
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

it('keeps which columns are on per form, so one form does not decide for another', function () {
    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
    $fields = [['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam', 'showColumn' => true]];
    $first = customForm($fields);
    $second = Form::create(['title' => 'Terugbellen', 'template' => 'custom', 'custom' => ['fields' => $fields]]);
    $table = fn (Form $form) => Livewire::test(FormSubmissionsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class]);

    $page = $table($first);
    expect($page->instance()->isTableColumnToggledHidden('data.voornaam'))->toBeFalse();

    $page->call('applyTableColumnManager', array_map(
        fn (array $column): array => $column['name'] === 'data.voornaam' ? [...$column, 'isToggled' => false] : $column,
        $page->get('tableColumns'),
    ));

    expect($table($first)->instance()->isTableColumnToggledHidden('data.voornaam'))->toBeTrue()
        ->and($table($second)->instance()->isTableColumnToggledHidden('data.voornaam'))->toBeFalse();
});
