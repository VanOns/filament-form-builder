<?php

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use VanOns\FilamentFormBuilder\Filament\Exporters\FormSubmissionExporter;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm;

afterEach(function () {
    FormSubmissionExporter::$form = null;
});

function formWithFields(string $title, array $labels): Form
{
    return Form::create([
        'title' => $title,
        'template' => CustomForm::class,
        'custom' => ['fields' => array_map(
            fn (string $label): array => ['type' => 'text', 'label' => $label],
            $labels,
        )],
    ]);
}

/**
 * @return array<string, string> column name => label
 */
function exportColumnMap(): array
{
    return collect(FormSubmissionExporter::getColumns())
        ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->getLabel()])
        ->all();
}

it('exports only the fields of the form it was opened from', function () {
    $sollicitatie = formWithFields('Solliciteren', ['Voornaam', 'Achternaam']);
    $klacht = formWithFields('Klacht', ['Onderwerp']);

    FormSubmission::create(['form_id' => $klacht->id, 'data' => ['onderwerp' => 'Te traag']]);

    FormSubmissionExporter::$form = $sollicitatie;

    expect(exportColumnMap())->toHaveKeys(['data.voornaam', 'data.achternaam'])
        ->and(exportColumnMap())->not->toHaveKey('data.onderwerp');
});

it('heads the columns with the labels the editor typed', function () {
    FormSubmissionExporter::$form = formWithFields('Solliciteren', ['Voornaam']);

    // Without the form it falls back to the key, headlined: "Voornaam" either
    // way here, but a key like `tel_nr` would read as "Tel Nr".
    expect(exportColumnMap()['data.voornaam'])->toBe('Voornaam');
});

it('keeps a column for a field the form no longer has', function () {
    // Renaming or removing a field does not touch the answers already given;
    // exporting only what the form asks today would drop them silently.
    $form = formWithFields('Solliciteren', ['Voornaam']);

    FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['voornaam' => 'Jesse', 'telefoon' => '0612345678'],
    ]);

    FormSubmissionExporter::$form = $form;

    expect(exportColumnMap())->toHaveKeys(['data.voornaam', 'data.telefoon'])
        ->and(exportColumnMap()['data.voornaam'])->toBe('Voornaam')
        ->and(exportColumnMap()['data.telefoon'])->toBe('Telefoon');
});

it('falls back to the keys found in the submissions', function () {
    $form = formWithFields('Solliciteren', ['Voornaam']);
    FormSubmission::create(['form_id' => $form->id, 'data' => ['voornaam' => 'Jesse']]);

    expect(exportColumnMap())->toHaveKey('data.voornaam');
});

it('gives the export job the columns of the modal, also for a field nobody answered yet', function () {
    $form = formWithFields('Solliciteren', ['Voornaam', 'Motivatie']);
    FormSubmission::create(['form_id' => $form->id, 'data' => ['voornaam' => 'Jesse']]);

    // The job runs in another process: the static form from the modal is gone.
    $exporter = new FormSubmissionExporter(new Export(), ['data.motivatie' => 'Motivatie'], ['form_id' => $form->id]);

    expect($exporter->getCachedColumns())->toHaveKey('data.motivatie');
});

it('reports how many submissions were exported', function () {
    $export = new Export(['total_rows' => 3, 'successful_rows' => 2]);

    expect(FormSubmissionExporter::getCompletedNotificationBody($export))
        ->toBe('The submissions export is ready: 2 rows exported. 1 row failed to export.');
});
