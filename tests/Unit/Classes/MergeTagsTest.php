<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Classes\MergeTags;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\EmailField;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

function tagged(string $id): string
{
    return '<span data-type="mergeTag" data-id="' . $id . '"></span>';
}

it('reads the placeholders from before as merge tags', function () {
    expect(MergeTags::fromLegacy('<p>Hoi {{ $naam }} en {{$achternaam}}</p>'))
        ->toBe('<p>Hoi ' . tagged('naam') . ' en ' . tagged('achternaam') . '</p>')
        // A subject was plain text: it becomes a paragraph, escaped.
        ->and(MergeTags::fromLegacy('Vraag van {{ $naam }} & co'))->toBe('<p>Vraag van ' . tagged('naam') . ' &amp; co</p>')
        // Inside a link the placeholder stays text.
        ->and(MergeTags::fromLegacy('<p><a href="https://example.test/?n={{ $naam }}">Link</a></p>'))
        ->toBe('<p><a href="https://example.test/?n={{ $naam }}">Link</a></p>');
});

it('fills in the tags, escaped, and leaves an unknown tag empty', function () {
    $html = '<p>Hoi ' . tagged('naam') . tagged('onbekend') . '!</p>';

    expect(MergeTags::render($html, ['naam' => '<b>Jan</b>']))->toBe('<p>Hoi &lt;b&gt;Jan&lt;/b&gt;!</p>')
        ->and(MergeTags::render('<p>' . tagged('blok') . '</p>', ['blok' => new HtmlString('<table></table>')]))->toBe('<p><table></table></p>')
        ->and(MergeTags::render('Vraag van {{ $naam }} & co', ['naam' => 'Jan'], asText: true))->toBe('Vraag van Jan & co');
});

it('never reads a filled-in answer as a tag', function () {
    expect(MergeTags::render('<p>' . tagged('naam') . '</p>', ['naam' => '{{ $geheim }}', 'geheim' => 'x']))
        ->toBe('<p>{{ $geheim }}</p>');
});

it('fills a placeholder inside a link in, escaped for the attribute', function () {
    expect(MergeTags::render('<p><a href="https://example.test/?n={{ $naam }}">Link</a></p>', ['naam' => 'Jan "de" Vries']))
        ->toContain('href="https://example.test/?n=Jan &quot;de&quot; Vries"');
});

it('finds and renames the tags in stored html and in the editor state', function () {
    $state = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'mergeTag', 'attrs' => ['id' => 'naam']],
    ]]]];

    expect(MergeTags::ids('<p>' . tagged('naam') . ' {{ $email }}</p>'))->toBe(['naam', 'email'])
        ->and(MergeTags::ids($state))->toBe(['naam'])
        ->and(MergeTags::ids(MergeTags::rename($state, 'naam', 'roepnaam')))->toBe(['roepnaam'])
        ->and(MergeTags::rename('<p>' . tagged('naam') . ' {{ $naam }}</p>', 'naam', 'roepnaam'))
        ->toBe('<p>' . tagged('roepnaam') . ' {{ $roepnaam }}</p>');
});

it('offers the answers of a form and what is known about a submission', function () {
    $form = Form::create(['title' => 'Contact', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
    ]]]);

    expect($form->getMergeTags())->toBe([
        'voornaam' => 'Voornaam',
        'form_title' => 'Form title',
        'all_fields' => 'All fields',
        'submission_id' => 'Submission number',
        'submitted_at' => 'Submitted at',
        'submitted_from' => 'Submitted from',
        'submission_url' => 'Link to the submission',
    ])
        ->and($form->getMergeTags(withAllFields: false, withSubmissionLink: false))->not->toHaveKeys(['all_fields', 'submission_url']);
});

it('groups the tags for the picker, each with the icon of what it stands for', function () {
    $form = Form::create(['title' => 'Contact', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'email', 'label' => 'E-mailadres', 'key' => 'email'],
    ]]]);

    $groups = $form->getMergeTagGroups(withAllFields: false);

    expect(array_column($groups, 'label'))->toBe(['Fields', 'Form', 'Submission'])
        ->and($groups[0]['tags']['email'])->toBe(['label' => 'E-mailadres', 'icon' => EmailField::icon()])
        ->and(array_keys($groups[1]['tags']))->toBe(['form_title'])
        ->and(array_keys($groups[2]['tags']))->toBe(['submission_id', 'submitted_at', 'submitted_from', 'submission_url']);
});

it('puts the submission and a link to it in a mail', function () {
    $form = Form::create(['title' => 'Contact', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
    ]]]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['voornaam' => 'Jan']]);

    $notification = new EmailNotification($submission, [
        'subject' => '<p>Inzending ' . tagged('submission_id') . ' van ' . tagged('voornaam') . '</p>',
        'content' => '<p>' . tagged('submission_url') . '</p>',
    ]);

    expect($notification->subject)->toBe("Inzending {$submission->id} van Jan")
        ->and($notification->content)->toContain('<a href="http://localhost/admin/form-submissions/' . $submission->id . '">View the submission</a>');
});

it('warns about tags that refer to a field the form no longer has', function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    $form = Form::create(['title' => 'Contact', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
    ]], 'notifications' => [
        ['subject' => 'Van {{ $voornaam }}', 'content' => '<p>{{ $aanhef }}</p>', 'receivers' => []],
    ]]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSee('The tag aanhef refers to a field that no longer exists.');
});
