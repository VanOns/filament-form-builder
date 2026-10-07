<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

const V2_FIELDS = 'VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\\';

function convertV2Migration(): object
{
    return require __DIR__ . '/../../../resources/boost/skills/filament-form-builder-v3-upgrade/references/convert-v2-forms.php';
}

function v2Form(string $title, string $template, array $fields = [], array $columns = []): int
{
    return DB::table('forms')->insertGetId([
        'title' => $title,
        'template' => $template,
        'custom' => json_encode(['fields' => $fields]),
        'notifications' => json_encode([[
            'subject' => 'Nieuw van {{ $key_voornaam }}',
            'content' => '<p>{{$key_voornaam}} schreef:</p><p>{{ $all_fields }}</p><p>{{ $submitter_email }}</p>',
            'sender' => 'site@example.com',
            'senderName' => '{{ $form_title }}',
            'receivers' => ['info@example.com', 'key_email_adres', 'submitter_email'],
        ]]),
        'submit_notifications' => json_encode([['id' => 'outcome', 'conditions' => [], 'conditionMatch' => 'all', 'type' => 'content', 'content' => '<p>Bedankt {{ $key_voornaam }}!</p>', 'url' => null, 'query' => 'naam={{ $key_voornaam }}']]),
        'created_at' => now(),
        'updated_at' => now(),
        ...$columns,
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function v2Fields(): array
{
    return [
        ['fieldType' => V2_FIELDS . 'TitleField', 'title' => 'Over jou', 'headingLevel' => 'h3'],
        ['fieldType' => V2_FIELDS . 'InputField', 'inputType' => 'text', 'label' => 'Voornaam', 'required' => true, 'large' => false, 'set_key' => false, 'column_span' => null],
        ['fieldType' => V2_FIELDS . 'InputField', 'inputType' => 'email', 'label' => 'E-mail', 'key' => 'email_adres', 'set_key' => true, 'large' => true],
        ['fieldType' => V2_FIELDS . 'InputField', 'inputType' => 'tel', 'label' => 'Telefoon', 'column_span' => 2, 'column_start' => 2],
        ['fieldType' => V2_FIELDS . 'SelectField', 'label' => 'Wil je teruggebeld worden?', 'key' => 'terugbellen', 'multiple' => false, 'placeholder' => 'Kies',
            'options' => [['value' => 'ja', 'label' => 'Ja'], ['value' => 'nee', 'label' => 'Nee']],
            'visibleWhenKey' => 'key_telefoon', 'visibleWhenType' => 'not_empty', 'visibleWhenValue' => null],
        ['fieldType' => V2_FIELDS . 'SelectField', 'label' => 'Interesses', 'multiple' => true, 'options' => [['value' => 'web', 'label' => 'Websites']],
            'visibleWhenKey' => 'terugbellen', 'visibleWhenType' => 'equals', 'visibleWhenValue' => 'ja'],
        ['fieldType' => V2_FIELDS . 'TextAreaField', 'label' => 'Bericht', 'rows' => 4],
        ['fieldType' => V2_FIELDS . 'FileUploadField', 'label' => 'Cv', 'multiple' => true],
        ['fieldType' => V2_FIELDS . 'CheckboxField', 'label' => 'Akkoord', 'required' => true],
        ['fieldType' => V2_FIELDS . 'TextField', 'text' => '<p>We bellen binnen een dag.</p>'],
        ['fieldType' => V2_FIELDS . 'SubmitField', 'label' => 'Versturen'],
    ];
}

it('turns the fields, notifications and outcomes of a v2 form into v3', function () {
    $id = v2Form('Contact', 'VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm', v2Fields());

    convertV2Migration()->up();

    $form = Form::findOrFail($id);
    $fields = collect($form->getFields());
    $byLabel = $fields->keyBy(fn (FormField $field): string => $field->label ?? $field::class);

    expect($form->template)->toBe('custom')
        ->and($fields->map(fn (FormField $field): string => array_search($field::class, config('filament-form-builder.fields'), true))->all())
        ->toBe(['title', 'text', 'email', 'phone', 'radio', 'checkbox_list', 'textarea', 'file_upload', 'checkbox', 'text_block', 'submit'])
        ->and(array_keys($form->getSubmissionFields()))->toBe(['voornaam', 'email_adres', 'telefoon', 'terugbellen', 'interesses', 'bericht', 'cv', 'akkoord'])
        // Two columns: one is half the row, two or large the whole of it.
        ->and($fields->map(fn (FormField $field): int => $field->getColumnSpan())->all())->toBe([12, 6, 12, 12, 6, 6, 6, 6, 6, 12, 12])
        ->and($byLabel['Wil je teruggebeld worden?']->getConditions()->toArray())->toBe(['match' => 'all', 'rules' => [['key' => 'telefoon', 'operator' => 'not_empty', 'value' => null]]])
        ->and($byLabel['Interesses']->getConditions()->toArray())->toBe(['match' => 'all', 'rules' => [['key' => 'terugbellen', 'operator' => 'equals', 'value' => 'ja']]])
        ->and($byLabel['Cv']->multiple)->toBeTrue()
        ->and($byLabel['Bericht']->rows)->toBe(4)
        ->and($form->getSubmitNotifications()[0])->toMatchArray(['content' => '<p>Bedankt {{ $voornaam }}!</p>', 'query' => 'naam={{ $voornaam }}']);

    $notification = EmailNotification::normalize($form->notifications[0]);

    expect($notification['to'])->toBe(['info@example.com', 'field:email_adres'])
        ->and($notification['id'])->not->toBeNull()
        ->and($notification['subject'])->toBe('Nieuw van {{ $voornaam }}');

    $submission = FormSubmission::create(['form_id' => $id, 'data' => ['voornaam' => 'Jan', 'email_adres' => 'jan@example.com']]);
    $mail = new EmailNotification($submission, $form->notifications[0]);

    expect($mail->subject)->toBe('Nieuw van Jan')
        ->and($mail->receivers)->toBe(['info@example.com', 'jan@example.com'])
        ->and($mail->content)->toContain('Jan schreef:')->toContain('jan@example.com');

    Route::middleware('web')->get('contact/{form}', fn (Form $form) => Blade::render('<x-render-form :form="$form" />', ['form' => $form]));

    $this->get("contact/{$id}")
        ->assertOk()
        ->assertSee(['name="voornaam"', 'name="email_adres"', 'name="interesses[]"', 'Over jou', 'Versturen'], escape: false);
});

it('turns the spans of a wider template into twelfths', function () {
    $migration = convertV2Migration();
    $migration->templates['App\Forms\Vacancy'] = 'custom';
    $migration->templateColumns['App\Forms\Vacancy'] = 3;

    $id = v2Form('Vacature', 'App\Forms\Vacancy', [
        ['fieldType' => V2_FIELDS . 'InputField', 'label' => 'Naam'],
        ['fieldType' => V2_FIELDS . 'InputField', 'label' => 'Adres', 'column_span' => 2],
        ['fieldType' => V2_FIELDS . 'InputField', 'label' => 'Motivatie', 'large' => true],
    ]);

    $migration->up();

    expect(array_map(fn (FormField $field): int => $field->getColumnSpan(), Form::findOrFail($id)->getFields()))->toBe([4, 8, 12]);
});

it('renames the answers of v2 submissions and moves their uploads into files', function () {
    $id = v2Form('Contact', 'VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm', v2Fields());
    $old = DB::table('form_submissions')->insertGetId(['form_id' => $id, 'created_at' => now(), 'updated_at' => now(), 'data' => json_encode([
        'key_voornaam' => 'Jan',
        'key_interesses' => ['web'],
        'key_cv' => ['https://example.com/filament-form-builder/file/form_uploads/a%20b.pdf', 'https://example.com/filament-form-builder/file/form_uploads/c.pdf'],
        'key_niets' => [],
    ])]);
    $new = DB::table('form_submissions')->insertGetId(['form_id' => $id, 'created_at' => now(), 'updated_at' => now(), 'field_snapshot' => '[]', 'data' => json_encode(['key_code' => 'blijft'])]);

    convertV2Migration()->up();

    expect(FormSubmission::findOrFail($old))
        ->data->toEqual(['voornaam' => 'Jan', 'interesses' => ['web'], 'niets' => []])
        ->files->toEqual(['cv' => [['path' => 'form_uploads/a b.pdf', 'name' => 'a b.pdf'], ['path' => 'form_uploads/c.pdf', 'name' => 'c.pdf']]])
        ->and(FormSubmission::findOrFail($new)->data)->toBe(['key_code' => 'blijft']);
});

it('changes nothing the second time, and nothing at all when a class does not map', function () {
    $id = v2Form('Contact', 'VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm', v2Fields());

    convertV2Migration()->up();
    $once = (array) DB::table('forms')->find($id);
    convertV2Migration()->up();

    expect((array) DB::table('forms')->find($id))->toBe($once);

    $unknown = v2Form('Offerte', 'App\Forms\Quote', [['fieldType' => 'App\Fields\Branch', 'label' => 'Vestiging']]);

    expect(fn () => convertV2Migration()->up())->toThrow(RuntimeException::class, 'App\Forms\Quote');
    expect(DB::table('forms')->find($unknown)->template)->toBe('App\Forms\Quote');
});
