<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fixtures\ApplicationForm;
use Tests\Fixtures\NewsletterIntegration;
use VanOns\FilamentFormBuilder\Enums\IntegrationStatus;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource\Pages\ViewFormSubmission;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
});

function viewedSubmission(): FormSubmission
{
    $form = Form::create([
        'title' => 'Solliciteren',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'email', 'label' => 'E-mailadres', 'key' => 'email'],
            ['type' => 'checkbox_list', 'label' => 'Interesses', 'key' => 'interesses', 'options' => [
                ['value' => 'web', 'label' => 'Websites'],
                ['value' => 'app', 'label' => 'Apps'],
            ]],
            ['type' => 'checkbox', 'label' => 'Privacy', 'key' => 'privacy'],
            ['type' => 'text', 'label' => 'Aanhef', 'key' => 'aanhef'],
            ['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv'],
        ]],
    ]);

    Storage::disk('local')->put('form_uploads/cv.pdf', '%PDF-1.4');

    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => [
            'naam' => 'Jan',
            'email' => 'jan@example.test',
            'interesses' => ['web', 'app'],
            'privacy' => '1',
            'aanhef' => 'Mevrouw',
            'bron_anders' => 'Via een collega',
            'opmerking' => 'Zie https://example.test/portfolio.',
        ],
        'files' => ['cv' => [['path' => 'form_uploads/cv.pdf', 'name' => 'cv-jan.pdf']]],
    ]);

    $form->update(['custom' => ['fields' => array_values(array_filter(
        $form->custom['fields'],
        fn (array $field): bool => $field['key'] !== 'aanhef',
    ))]]);

    FormSubmissionNotificationLog::create([
        'form_submission_id' => $submission->id,
        'notification_subject' => 'Nieuwe sollicitatie',
        'recipient' => 'hr@example.test',
        'status' => 'sent',
    ]);

    return $submission->fresh();
}

it('shows every answer the way its field reads', function () {
    Livewire::test(ViewFormSubmission::class, ['record' => viewedSubmission()->getKey()])
        ->assertOk()
        ->assertSee('Submission #')
        ->assertSee('Naam')
        ->assertSeeHtml('href="mailto:jan@example.test"')
        ->assertSee('Websites')
        ->assertSee('Apps')
        ->assertSee('Yes')
        ->assertSeeHtml('<a href="https://example.test/portfolio" target="_blank" rel="noopener noreferrer">https://example.test/portfolio</a>.');
});

it('shows the answers the form no longer asks for apart', function () {
    Livewire::test(ViewFormSubmission::class, ['record' => viewedSubmission()->getKey()])
        ->assertSee('No longer in the form')
        ->assertSee('3 answers to fields that have since been removed. They are kept.')
        ->assertSee('Aanhef')
        ->assertSee('removed field')
        ->assertSee('Bron Anders')
        ->assertSee('unknown');
});

it('lists the files and the notifications beside the answers', function () {
    Livewire::test(ViewFormSubmission::class, ['record' => viewedSubmission()->getKey()])
        ->assertSee('cv-jan.pdf')
        ->assertSee('8 B · application/pdf')
        ->assertSee('View cv-jan.pdf')
        ->assertSee('Download cv-jan.pdf')
        ->assertSee('hr@example.test')
        ->assertSee('1 of 1 sent');
});

it('lays the answers out as the form does, under its titles and at its widths', function () {
    $form = Form::create(['title' => 'Offerte', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'title', 'title' => 'Over jou'],
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam', 'column_span' => 6],
        ['type' => 'text', 'label' => 'Achternaam', 'key' => 'achternaam', 'column_span' => 6],
        ['type' => 'title', 'title' => 'De opdracht'],
        ['type' => 'text', 'label' => 'Vacature', 'key' => 'vacature', 'hidden' => true],
        ['type' => 'textarea', 'label' => 'Toelichting', 'key' => 'toelichting'],
    ]]]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['voornaam' => 'Jan', 'achternaam' => 'de Vries', 'vacature' => 'Adviseur']]);

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->assertSeeHtml('href="' . FormResource::getUrl('edit', ['record' => $form]) . '"')
        ->assertSeeInOrder(['Offerte', ' · ' . $submission->created_at->translatedFormat('j F Y, H:i')])
        ->assertSeeInOrder(['Over jou', 'Voornaam', 'Jan', 'Achternaam', 'De opdracht', 'Vacature', 'hidden field', 'Adviseur', 'Toelichting', '—'])
        ->assertSeeHtml('--ffb-span: 6');
});

it('reads the answers as a list instead, and keeps to that for the next one', function () {
    $submission = viewedSubmission();

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->assertSeeHtml('ffb-answer-groups')
        ->assertSee('As a list')
        ->callAction(TestAction::make('answersLayout')->schemaComponent('answers', schema: 'infolist'))
        ->assertSeeHtml('class="ffb-answers"')
        ->assertDontSeeHtml('ffb-answer-groups')
        ->assertSee('As a form');

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->assertSet('answersLayout', 'list');
});

it('lists the values a form type adds with the details', function () {
    config(['filament-form-builder.types.application' => ApplicationForm::class]);
    $form = Form::create(['title' => 'Sollicitatie', 'template' => 'application']);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Jan', 'ontvangen_via' => 'website']]);

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->assertSeeInOrder(['Details', 'Form type', 'Ontvangen via', 'website']);
});

it('links a value a form type adds when it is an address', function () {
    config(['filament-form-builder.types.application' => ApplicationForm::class]);
    $form = Form::create(['title' => 'Sollicitatie', 'template' => 'application']);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Jan', 'ontvangen_via' => 'https://example.test/vacatures/adviseur']]);

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->assertSeeHtml('href="https://example.test/vacatures/adviseur"');
});

it('links to the page the form was sent from', function () {
    $submission = viewedSubmission();
    $submission->update(['source_url' => 'https://example.test/werken-bij']);

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->assertSee('Submitted from')
        ->assertSeeHtml('href="https://example.test/werken-bij"');
});

it('shows what the integrations did before v3', function () {
    $submission = viewedSubmission();
    $submission->update(['integrations' => [[
        'integration' => 'App\\Integrations\\RecruiteeIntegration',
        'response' => ['response' => ['candidate_id' => '48213'], 'success' => true],
        'ran_at' => '2026-10-07T14:33:05+00:00',
    ]]]);

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->assertSee(['RecruiteeIntegration', 'Succeeded', '7 Oct, 14:33'])
        ->mountAction(TestAction::make('integrationResponse')->schemaComponent('integration-runs', schema: 'infolist')->arguments(['row' => 'legacy-0']))
        ->assertMountedActionModalSee(['Response from RecruiteeIntegration', 'candidate_id', '48213']);
});

it('shows how each integration went, and why one did not run', function () {
    config(['filament-form-builder.integrations' => [NewsletterIntegration::class]]);
    $submission = viewedSubmission();
    $submission->form->update(['integrations' => [['id' => 'news', 'class' => NewsletterIntegration::class, 'list' => 'Klanten']]]);
    $log = fn (array $attributes) => $submission->integrationLogs()->create(['integration_id' => 'news', 'integration' => NewsletterIntegration::class, ...$attributes]);

    $log(['status' => 'succeeded', 'ran_at' => now(), 'response' => ['subscribed' => 'jan@example.test']]);
    $log(['status' => 'failed', 'ran_at' => now(), 'attempts' => 3, 'error' => 'Already on the list.']);
    $log(['status' => 'skipped']);

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->assertSee(['1 of 2 succeeded', 'Newsletter', 'List Klanten', 'Succeeded', 'Failed', 'Already on the list.', '3 attempts', 'Skipped', 'The answers did not meet the conditions.', 'Run again']);
});

it('runs an integration again', function () {
    config(['filament-form-builder.integrations' => [NewsletterIntegration::class]]);
    NewsletterIntegration::$sent = [];
    $submission = viewedSubmission();
    $submission->form->update(['integrations' => [['id' => 'news', 'class' => NewsletterIntegration::class, 'list' => 'Klanten', 'mapping' => ['email' => 'email']]]]);
    $log = $submission->integrationLogs()->create(['integration_id' => 'news', 'integration' => NewsletterIntegration::class, 'status' => 'failed', 'attempts' => 3, 'error' => 'Timed out']);

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->callAction(TestAction::make('rerunIntegration')->schemaComponent('integration-runs', schema: 'infolist')->arguments(['row' => "log-{$log->id}"]))
        ->assertNotified('Newsletter: Succeeded')
        ->assertDontSee('Timed out');

    expect($log->fresh())
        ->status->toBe(IntegrationStatus::Succeeded)
        ->error->toBeNull()
        ->attempts->toBe(1)
        ->and(NewsletterIntegration::$sent[0]['email'])->toBe('jan@example.test');
});

it('lists where a submission came from with the details', function () {
    $submission = viewedSubmission();
    $submission->update(['meta' => [
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/141.0.0.0 Safari/537.36',
        'locale' => 'nl',
        'user' => ['id' => 12, 'name' => 'Jan'],
        'campaign' => ['source' => 'nieuwsbrief'],
        'ip' => '203.0.113.0',
    ]]);

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->assertSeeInOrder(['Browser', 'Chrome · macOS', 'Language', 'Signed in as', 'Jan (#12)', 'Campaign', 'source: nieuwsbrief', 'IP address', '203.0.113.0']);
});

it('keeps a typed script out of the page', function () {
    $form = Form::create(['title' => 'Contact', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'text', 'label' => 'Bericht', 'key' => 'bericht'],
    ]]]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => [
        'bericht' => '<script>alert(1)</script> javascript:alert(1) https://example.test/"onmouseover="alert(1)',
    ]]);

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertDontSeeHtml('href="javascript')
        ->assertDontSeeHtml('"onmouseover="');
});
