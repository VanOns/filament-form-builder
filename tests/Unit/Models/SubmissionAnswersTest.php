<?php

use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use VanOns\FilamentFormBuilder\Classes\SubmissionAnswer;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\NumberField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class EstablishmentField extends TextInputField
{
    public function getSubmissionColumns(): array
    {
        return [
            $this->getKey() => 'Vestiging',
            'vestiging_naam' => 'Vestiging naam',
            'vestiging_email' => 'Vestiging e-mailadres',
        ];
    }
}

class VacancyForm extends FormType
{
    public function fields(): array
    {
        return [
            TextInputField::make('naam')->label('Naam'),
            EstablishmentField::make('vestiging')->label('Vestiging'),
            NumberField::make('uren')->label('Uren per week')
                ->formatAnswerUsing(fn (mixed $value): string => "{$value} uur per week"),
            TextInputField::make('cv_link')->label('Cv')->answerView('answers.cv'),
            CheckboxField::make('privacy')->label('Privacy'),
        ];
    }

    public function extraValues(): array
    {
        return ['pagina' => 'Verstuurd vanaf'];
    }
}

beforeEach(function () {
    config(['filament-form-builder.types.vacancy' => VacancyForm::class]);
});

function vacancySubmission(array $data): FormSubmission
{
    $form = Form::firstOrCreate(['title' => 'Vacature'], ['template' => 'vacancy']);

    return FormSubmission::create(['form_id' => $form->id, 'data' => $data]);
}

/**
 * @param  list<SubmissionAnswer>  $answers
 * @return array<string, SubmissionAnswer>
 */
function byKey(array $answers): array
{
    return collect($answers)->keyBy('key')->all();
}

it('keeps the fields of its form as they were when it was submitted', function () {
    $submission = vacancySubmission(['naam' => 'Jan']);

    expect($submission->field_snapshot['naam'])->toBe([
        'label' => 'Naam',
        'type' => 'text',
        'columns' => ['naam' => 'Naam'],
        'options' => [],
        'title' => null,
        'span' => 12,
        'new_row' => false,
    ])
        ->and($submission->field_snapshot['vestiging']['type'])->toBe(EstablishmentField::class)
        ->and($submission->field_snapshot['pagina']['label'])->toBe('Verstuurd vanaf');
});

it('keeps the order of the form where the database sorts the keys of JSON', function () {
    $submission = vacancySubmission(['naam' => 'Jan']);
    $stored = json_decode((string) DB::table('form_submissions')->where('id', $submission->id)->value('field_snapshot'), true);
    $order = ['naam', 'vestiging', 'uren', 'cv_link', 'privacy', 'pagina'];

    // MySQL would put uren before vestiging, and pagina before cv_link.
    expect(array_column($stored, 'key'))->toBe($order)
        ->and(array_keys($submission->fresh()->field_snapshot))->toBe($order)
        ->and($submission->fresh()->field_snapshot['vestiging']['columns'])->toBe([
            'vestiging' => 'Vestiging',
            'vestiging_naam' => 'Vestiging naam',
            'vestiging_email' => 'Vestiging e-mailadres',
        ]);
});

it('shows a field that fills several columns as one answer', function () {
    $answers = byKey(vacancySubmission([
        'vestiging' => '1042',
        'vestiging_naam' => 'Utrecht Centrum',
        'vestiging_email' => '',
    ])->getAnswers()['current']);

    expect($answers['vestiging']->value)->toBe(['vestiging' => '1042', 'vestiging_naam' => 'Utrecht Centrum'])
        ->and($answers['vestiging']->view)->toBe('filament-form-builder::answers.columns')
        ->and($answers['vestiging']->columns)->toHaveCount(3)
        ->and($answers['vestiging']->note)->toBe('3 columns in the table')
        ->and($answers)->not->toHaveKey('vestiging_naam');
});

it('leaves out the fields nobody answered', function () {
    $answers = byKey(vacancySubmission(['naam' => 'Jan', 'uren' => ''])->getAnswers()['current']);

    expect(array_keys($answers))->toBe(['naam']);
});

it('keeps the values a form type adds apart from the answers, for the details', function () {
    $submission = vacancySubmission(['pagina' => 'https://example.test/vacature']);
    $answers = byKey($submission->getTypeAnswers());

    expect($answers['pagina']->label)->toBe('Verstuurd vanaf')
        ->and($answers['pagina']->icon)->toBe(Heroicon::OutlinedCube)
        ->and(byKey($submission->getAnswers()['current']))->not->toHaveKey('pagina');
});

it('formats an answer the way its field asks, everywhere it is shown', function () {
    $submission = vacancySubmission(['uren' => '32']);

    expect($submission->getFormattedData())->toBe(['uren' => '32 uur per week'])
        ->and(byKey($submission->getAnswers()['current'])['uren']->value)->toBe('32 uur per week')
        ->and(byKey($submission->getAnswers()['current'])['uren']->raw)->toBe('32');
});

it('shows an answer through the view its field names', function () {
    $answers = byKey(vacancySubmission(['cv_link' => 'cv.pdf'])->getAnswers()['current']);

    expect($answers['cv_link']->view)->toBe('answers.cv');
});

it('reads a checkbox as yes or no', function () {
    expect(vacancySubmission(['privacy' => '1'])->getDisplayText('privacy'))->toBe('Yes')
        ->and(vacancySubmission(['privacy' => '0'])->getDisplayText('privacy'))->toBe('No');
});

it('keeps the answers of a removed field apart, under the label it had', function () {
    $form = Form::create([
        'title' => 'Contact',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'radio', 'label' => 'Aanhef', 'key' => 'aanhef', 'options' => [
                ['value' => 'mw', 'label' => 'Mevrouw'],
            ]],
        ]],
    ]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Jan', 'aanhef' => 'mw']]);

    $form->update(['custom' => ['fields' => [['type' => 'text', 'label' => 'Naam', 'key' => 'naam']]]]);
    $submission = $submission->fresh();

    $answers = $submission->getAnswers();

    expect(array_keys(byKey($answers['current'])))->toBe(['naam'])
        ->and($answers['removed'])->toHaveCount(1)
        ->and($answers['removed'][0]->label)->toBe('Aanhef')
        ->and($answers['removed'][0]->value)->toBe('Mevrouw')
        ->and($answers['removed'][0]->badge)->toBe('removed field')
        ->and($answers['removed'][0]->note)->toBe('was radio buttons')
        ->and($submission->findLabel('aanhef'))->toBe('Aanhef');
});

it('shows a key nothing knows about as unknown', function () {
    $form = Form::create(['title' => 'Contact', 'template' => 'custom', 'custom' => ['fields' => []]]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['bron_anders' => 'Via een collega']]);

    $removed = $submission->getAnswers()['removed'];

    expect($removed)->toHaveCount(1)
        ->and($removed[0]->label)->toBe('Bron Anders')
        ->and($removed[0]->badge)->toBe('unknown')
        ->and($removed[0]->note)->toBe('no label kept');
});

it('shows a file with the field it was uploaded to, also once that field is gone', function () {
    $form = Form::create([
        'title' => 'Solliciteren',
        'template' => 'custom',
        'custom' => ['fields' => [['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv'], ['type' => 'file_upload', 'label' => 'Portfolio', 'key' => 'portfolio']]],
    ]);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => [],
        'files' => ['cv' => [['path' => 'form_uploads/cv.pdf', 'name' => 'cv.pdf']], 'portfolio' => [['path' => 'form_uploads/werk.zip', 'name' => 'werk.zip']]],
    ]);
    $form->update(['custom' => ['fields' => [['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv']]]]);

    ['current' => $current, 'removed' => $removed] = $submission->fresh()->getAnswers();

    expect($current)->toHaveCount(1)
        ->and($current[0]->value)->toBeNull()
        ->and(array_map(fn ($file) => $file->name, $current[0]->files))->toBe(['cv.pdf'])
        ->and($removed)->toHaveCount(1)
        ->and($removed[0]->label)->toBe('Portfolio')
        ->and(array_map(fn ($file) => $file->name, $removed[0]->files))->toBe(['werk.zip']);
});

/**
 * @return list<array{title: ?string, fields: list<string>}>
 */
function layoutOf(FormSubmission $submission): array
{
    return array_map(fn (array $group): array => [
        'title' => $group['title'],
        'fields' => array_map(fn (array $cell): string => $cell['field']->getKey() . ':' . $cell['span'], $group['fields']),
    ], $submission->getAnswerGroups());
}

it('keeps the layout its form had when it came in, however often the form changes', function () {
    $form = Form::create(['title' => 'Offerte', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'title', 'title' => 'Over jou'],
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam', 'column_span' => 6],
        ['type' => 'text', 'label' => 'Achternaam', 'key' => 'achternaam', 'column_span' => 6],
        ['type' => 'textarea', 'label' => 'Bericht', 'key' => 'bericht'],
    ]]]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['voornaam' => 'Jan', 'bericht' => 'Hallo']]);

    $form->update(['custom' => ['fields' => [
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
        ['type' => 'text', 'label' => 'Achternaam', 'key' => 'achternaam'],
        ['type' => 'textarea', 'label' => 'Bericht', 'key' => 'bericht'],
    ]]]);
    $form->update(['custom' => ['fields' => [
        ['type' => 'title', 'title' => 'Contact'],
        ['type' => 'phone', 'label' => 'Telefoon', 'key' => 'telefoon'],
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
        ['type' => 'textarea', 'label' => 'Bericht', 'key' => 'bericht'],
    ]]]);

    expect(layoutOf($submission->fresh()))->toBe([
        ['title' => 'Over jou', 'fields' => ['voornaam:6', 'bericht:12']],
    ])->and(layoutOf(FormSubmission::create(['form_id' => $form->id, 'data' => []])))->toBe([
        ['title' => 'Contact', 'fields' => ['telefoon:12', 'voornaam:12', 'bericht:12']],
    ]);
});

it('follows the form now for a submission that came in before forms kept their layout', function () {
    $form = Form::create(['title' => 'Offerte', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'title', 'title' => 'Over jou'],
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam', 'column_span' => 6],
    ]]]);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['voornaam' => 'Jan'],
        'field_snapshot' => ['voornaam' => ['label' => 'Voornaam', 'type' => 'text', 'columns' => ['voornaam' => 'Voornaam'], 'options' => []]],
    ]);

    expect(layoutOf($submission))->toBe([['title' => 'Over jou', 'fields' => ['voornaam:6']]]);
});
