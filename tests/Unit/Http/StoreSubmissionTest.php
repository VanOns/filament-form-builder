<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Event;
use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmitted;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class BranchField extends TextInputField
{
    public function getSubmissionColumns(): array
    {
        return [$this->getKey() => 'Vestiging', 'branch_email' => 'Vestiging e-mailadres'];
    }
}

function submit(Form $form, array $payload): FormSubmission
{
    test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), $payload);

    return FormSubmission::query()->where('form_id', $form->id)->sole();
}

it('stores only the fields a custom form asks for', function () {
    $form = Form::create([
        'title' => 'Terugbellen',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'title', 'title' => 'Bel me terug'],
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
        ]],
    ]);

    $submission = submit($form, [
        'naam' => 'Jan',
        'title_field' => 'not a field',
        'is_admin' => '1',
    ]);

    expect($submission->data)->toEqual(['naam' => 'Jan']);
});

it('stores only the fields a form type has in code', function () {
    $form = Form::create(['title' => 'Contact', 'template' => 'contact']);

    $submission = submit($form, [
        'name' => 'Jan',
        'company_name' => 'Van Ons',
        'email' => 'jan@example.com',
        'phone_number' => '0612345678',
        'message' => 'Hallo',
        'is_admin' => '1',
    ]);

    expect($submission->data)->toEqual([
        'name' => 'Jan',
        'company_name' => 'Van Ons',
        'email' => 'jan@example.com',
        'phone_number' => '0612345678',
        'message' => 'Hallo',
    ]);
});

it('accepts a form whose optional fields were left empty', function () {
    $form = Form::create([
        'title' => 'Terugbellen',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'email', 'label' => 'E-mail', 'key' => 'email'],
            ['type' => 'dropdown', 'label' => 'Land', 'key' => 'land', 'options' => [['value' => 'nl', 'label' => 'Nederland']]],
        ]],
    ]);

    expect(submit($form, ['email' => '', 'land' => ''])->data)->toEqual(['email' => null, 'land' => null]);
});

it('never takes a column a field fills in itself from the visitor', function () {
    config(['filament-form-builder.fields.branch' => BranchField::class]);

    $form = Form::create([
        'title' => 'Afspraak',
        'template' => 'custom',
        'custom' => ['fields' => [['type' => 'branch', 'label' => 'Vestiging', 'key' => 'vestiging']]],
    ]);

    expect(submit($form, ['vestiging' => '12', 'branch_email' => 'iemand@example.test'])->data)->toBe(['vestiging' => '12'])
        ->and($form->getSubmissionFields())->toHaveKey('branch_email');
});

it('remembers the page a form was sent from, for its merge tag', function () {
    $form = Form::create(['title' => 'Terugbellen', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
    ]]]);

    test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['naam' => 'Jan'], ['referer' => 'https://example.test/contact?bron=nieuwsbrief']);
    $submission = FormSubmission::sole();

    expect($submission->source_url)->toBe('https://example.test/contact?bron=nieuwsbrief')
        ->and(SubmissionPlaceholders::make($submission)->values()['submitted_from'])->toBe('https://example.test/contact?bron=nieuwsbrief')
        ->and(array_keys($form->getMergeTagGroups()[2]['tags']))->toContain('submitted_from');
});

it('keeps where a submission came from as far as the config allows', function (mixed $ip, ?string $stored) {
    config(['filament-form-builder.submission_meta.ip' => $ip]);
    test()->actingAs($user = User::forceCreate(['name' => 'Jan', 'email' => 'jan@example.test', 'password' => 'secret']));
    $form = Form::create(['title' => 'Terugbellen', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
    ]]]);

    test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['naam' => 'Jan'], [
        'referer' => 'https://example.test/contact?utm_source=nieuwsbrief&utm_campaign=najaar',
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/141.0.0.0 Safari/537.36',
    ]);

    expect(FormSubmission::sole()->meta)->toEqual(array_filter([
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/141.0.0.0 Safari/537.36',
        'locale' => 'en',
        'user' => ['id' => $user->id, 'name' => 'Jan'],
        'campaign' => ['source' => 'nieuwsbrief', 'campaign' => 'najaar'],
        'ip' => $stored,
    ]));
})->with([
    'without the address' => [false, null],
    'with part of it' => ['anonymized', '127.0.0.0'],
    'with all of it' => ['full', '127.0.0.1'],
]);

it('tells the app a visitor sent a form, but not about a submission made in code', function () {
    Event::fake([FormSubmitted::class]);
    $form = Form::create(['title' => 'Terugbellen', 'template' => 'custom', 'custom' => ['fields' => [['type' => 'text', 'label' => 'Naam', 'key' => 'naam']]]]);

    $submission = submit($form, ['naam' => 'Jan']);
    FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Import']]);

    Event::assertDispatchedTimes(FormSubmitted::class, 1);
    Event::assertDispatched(FormSubmitted::class, fn (FormSubmitted $event): bool => $event->formSubmission->is($submission));
});
