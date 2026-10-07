<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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
        ->assertSee('Aanhef')
        ->assertSee('removed field')
        ->assertSee('Bron Anders')
        ->assertSee('unknown');
});

it('lists the files and the notifications beside the answers', function () {
    Livewire::test(ViewFormSubmission::class, ['record' => viewedSubmission()->getKey()])
        ->assertSee('cv-jan.pdf')
        ->assertSee('View cv-jan.pdf')
        ->assertSee('Download cv-jan.pdf')
        ->assertSee('hr@example.test')
        ->assertSee('1 of 1 sent');
});

it('links to the page the form was sent from', function () {
    $submission = viewedSubmission();
    $submission->update(['source_url' => 'https://example.test/werken-bij']);

    Livewire::test(ViewFormSubmission::class, ['record' => $submission->getKey()])
        ->assertSee('Submitted from')
        ->assertSeeHtml('href="https://example.test/werken-bij"');
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
