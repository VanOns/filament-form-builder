<?php

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\Exporters\FormSubmissionExporter;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\RelationManagers\FormSubmissionsRelationManager;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

afterEach(function () {
    FormSubmissionExporter::$form = null;
});

function formWithFields(string $title, array $labels): Form
{
    return Form::create([
        'title' => $title,
        'template' => 'custom',
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

it('puts the answers the form no longer asks for in one column', function () {
    // Renaming or removing a field does not touch the answers already given;
    // exporting only what the form asks today would drop them silently.
    $form = Form::create([
        'title' => 'Solliciteren',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
            ['type' => 'text', 'label' => 'Telefoonnummer', 'key' => 'telefoon'],
            ['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv'],
        ]],
    ]);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['voornaam' => 'Jesse', 'telefoon' => '0612345678', 'bron' => 'LinkedIn'],
        'files' => ['cv' => [['path' => 'form_uploads/cv.pdf', 'name' => 'cv.pdf']]],
    ]);
    $form->update(['custom' => ['fields' => [['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam']]]]);

    FormSubmissionExporter::$form = $form->fresh();

    expect(exportColumnMap())->toHaveKey('other_data', 'Other data')
        ->not->toHaveKey('data.telefoon')
        ->not->toHaveKey('data.bron');

    $exporter = new FormSubmissionExporter(new Export(), ['data.voornaam' => 'Voornaam', 'other_data' => 'Other data'], ['form_id' => $form->id]);
    [$voornaam, $other] = $exporter($submission->fresh());

    expect($voornaam)->toBe('Jesse')
        ->and(explode("\n", $other))->toHaveCount(3)
        ->and(explode("\n", $other)[0])->toBe('Telefoonnummer: 0612345678')
        ->and(explode("\n", $other)[1])->toStartWith('CV: http')
        ->and(explode("\n", $other)[2])->toBe('Bron: LinkedIn');
});

it('leaves the other data empty when the form still asks for everything', function () {
    $form = formWithFields('Solliciteren', ['Voornaam']);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['voornaam' => 'Jesse']]);

    $exporter = new FormSubmissionExporter(new Export(), ['other_data' => 'Other data'], ['form_id' => $form->id]);

    expect($exporter($submission))->toBe([null]);
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

it('builds an export across forms from the columns that were picked', function () {
    $form = formWithFields('Solliciteren', ['Voornaam']);
    FormSubmission::create(['form_id' => $form->id, 'data' => ['voornaam' => 'Jesse', 'telefoon' => '0612345678']]);

    $exporter = new FormSubmissionExporter(new Export(), ['data.voornaam' => 'Voornaam'], []);

    expect($exporter->getCachedColumns())->toHaveKey('data.voornaam')
        ->not->toHaveKey('data.telefoon');
});

it('reports how many submissions were exported', function () {
    $export = new Export(['total_rows' => 3, 'successful_rows' => 2]);

    expect(FormSubmissionExporter::getCompletedNotificationBody($export))
        ->toBe('The submissions export is ready: 2 rows exported. 1 row failed to export.');
});

it('offers the export unless the config turns it off', function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
    $form = formWithFields('Solliciteren', ['Voornaam']);
    $table = fn () => Livewire::test(FormSubmissionsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class]);

    $table()->assertActionVisible(TestAction::make('export')->table());

    config(['filament-form-builder.export_action' => false]);

    $table()->assertActionHidden(TestAction::make('export')->table());

    FilamentFormBuilderPlugin::get()->exportAction();

    $table()->assertActionVisible(TestAction::make('export')->table());
});

it('lists the forms in the navigation group a panel sets', function () {
    $plugin = FilamentFormBuilderPlugin::get();

    expect(FormResource::getNavigationGroup())->toBe(__('filament-form-builder::general.navigation-group'))
        ->and($plugin->navigationGroup('Website')->getNavigationGroup())->toBe('Website')
        ->and(FormSubmissionResource::getNavigationGroup())->toBe('Website')
        ->and($plugin->navigationGroup(false)->getNavigationGroup())->toBeNull();
});
