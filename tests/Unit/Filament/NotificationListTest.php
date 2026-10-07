<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Classes\MergeTags;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\MergeTagEditor;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\ViewForm;
use VanOns\FilamentFormBuilder\Mail\FormSubmission\FormSubmissionCreatedMail;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

beforeEach(function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
});

function notifiedForm(array $notifications = []): Form
{
    return Form::create([
        'title' => 'Solliciteren',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'email', 'label' => 'E-mailadres', 'key' => 'email'],
        ]],
        'submit_notifications' => [['type' => 'content', 'content' => '<p>Bedankt!</p>']],
        'notifications' => $notifications,
    ]);
}

function onNotifications(string $action, array $arguments = []): TestAction
{
    return TestAction::make($action)->schemaComponent('notifications', schema: 'form')->arguments($arguments);
}

function editNotifications(Form $form)
{
    return Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
}

it('gives every notification an id and the current shape once the form is saved', function () {
    $form = notifiedForm([['subject' => 'Van {{ $naam }}', 'content' => '<p>Hoi</p>', 'to' => ['field:email']]]);

    editNotifications($form)->call('save');

    $notification = $form->refresh()->notifications[0];

    expect($notification['id'])->toBeString()->not->toBeEmpty()
        ->and($notification['to'])->toBe(['field:email'])
        ->and($notification['enabled'])->toBeTrue()
        ->and(MergeTags::ids($notification['subject']))->toBe(['naam']);
});

it('starts a confirmation to the person who sent the form', function () {
    $form = notifiedForm();

    editNotifications($form)
        ->mountAction(onNotifications('add', ['preset' => 'confirmation']))
        ->assertSchemaStateSet(['to' => ['field:email']])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->call('save');

    $notification = $form->refresh()->notifications[0];

    expect($notification['to'])->toBe(['field:email'])
        ->and(MergeTags::ids($notification['content']))->toBe(['all_fields']);
});

it('saves an edit, a switch and a delete straight away, without saving the page', function () {
    $form = notifiedForm([['id' => 'team', 'subject' => 'Nieuw', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test']]]);
    $page = editNotifications($form);

    $page->callAction(onNotifications('edit', ['item' => 'team']), data: [
        'to' => ['field:email', 'hr@example.test'],
        'reply_to' => 'field:email',
        'when' => 'conditions',
        'conditions' => [['key' => 'naam', 'operator' => 'not_empty']],
    ])->assertHasNoActionErrors();

    $page->callAction(onNotifications('toggle', ['item' => 'team']));

    $notification = $form->refresh()->notifications[0];

    expect($notification['to'])->toBe(['field:email', 'hr@example.test'])
        ->and($notification['reply_to'])->toBe('field:email')
        ->and($notification['conditions'][0]['key'])->toBe('naam')
        ->and($notification['enabled'])->toBeFalse()
        ->and($notification)->not->toHaveKeys(['when', 'show_sender']);

    $page->callAction(onNotifications('delete', ['item' => 'team']));

    expect($form->refresh()->notifications)->toBe([]);
});

it('refuses an address that is none, a field that holds no address and no recipients at all', function () {
    $form = notifiedForm([['id' => 'team', 'subject' => 'Nieuw', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test']]]);

    $errors = fn (array $to): array => editNotifications($form)
        ->callAction(onNotifications('edit', ['item' => 'team']), data: ['to' => $to])
        ->errors()
        ->get('mountedActions.0.data.to');

    expect($errors(['geen adres']))->toBe(['geen adres is not an e-mail address.'])
        ->and($errors(['field:naam']))->toBe(['Remove Naam (not an e-mail field): that field mails nobody.'])
        ->and($errors([]))->toBe(['Pick a field or type at least one address.']);
});

it('marks a recipient field that mails nobody', function () {
    $form = notifiedForm([['id' => 'team', 'subject' => 'Nieuw', 'content' => '<p>Hoi</p>', 'to' => ['field:naam', 'field:weg']]]);

    editNotifications($form)
        ->assertSee('Naam (not an e-mail field)')
        ->assertSee('weg (no longer exists)')
        ->assertSeeHtml('ffb-recipient-broken');
});

it('keeps copies only once they are asked for, and shows them on the card', function () {
    $form = notifiedForm([['id' => 'team', 'subject' => 'Nieuw', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test']]]);
    $page = editNotifications($form);

    $page->mountAction(onNotifications('edit', ['item' => 'team']))
        ->assertSchemaStateSet(['show_copies' => false])
        ->fillForm(['show_copies' => true, 'cc' => ['baas@example.test'], 'bcc' => ['field:email']])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertSee('baas@example.test');

    expect($form->refresh()->notifications[0])
        ->cc->toBe(['baas@example.test'])
        ->bcc->toBe(['field:email']);

    $page->mountAction(onNotifications('edit', ['item' => 'team']))
        ->assertSchemaStateSet(['show_copies' => true])
        ->fillForm(['show_copies' => false])
        ->callMountedAction();

    expect($form->refresh()->notifications[0])->cc->toBe([])->bcc->toBe([]);
});

it('goes back to the default sender once the sender fields are put away', function () {
    $form = notifiedForm([['id' => 'team', 'subject' => 'Nieuw', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test'],
        'sender' => 'jobs@example.test', 'senderName' => '<p><span data-type="mergeTag" data-id="naam"></span></p>']]);

    editNotifications($form)
        ->mountAction(onNotifications('edit', ['item' => 'team']))
        ->assertSchemaStateSet(['show_sender' => true])
        ->fillForm(['show_sender' => false])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($form->refresh()->notifications[0])
        ->sender->toBeNull()
        ->senderName->toBeNull();
});

it('gives every tag the icon of its field, and marks one the form no longer has', function () {
    $page = editNotifications(notifiedForm())->instance();
    $subject = '<p><span data-type="mergeTag" data-id="naam"></span> <span data-type="mergeTag" data-id="weg"></span></p>';

    expect(MergeTagEditor::icons($page, $subject)->toHtml())
        ->toContain('span[data-type="mergeTag"][data-id="naam"]{--ffb-tag-icon:url("data:image/svg+xml,')
        ->toContain('span[data-type="mergeTag"][data-id="weg"]{--ffb-tag-icon:var(--ffb-tag-icon-missing);')
        ->not->toContain('data-id="all_fields"]{--ffb-tag-icon:var(--ffb-tag-icon-missing)');
});

it('asks whether a box is ticked instead of for a value', function () {
    $form = notifiedForm([['id' => 'team', 'subject' => 'Nieuw', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test'],
        'conditions' => [['key' => 'akkoord', 'operator' => 'not_empty']]]]);
    $form->update(['custom' => ['fields' => [...$form->custom['fields'], ['type' => 'checkbox', 'label' => 'Akkoord', 'key' => 'akkoord']]]]);

    editNotifications($form)->assertSee('If Akkoord is ticked');
});

it('sends a test mail to the person editing, filled with the latest submission', function () {
    Mail::fake();

    $form = notifiedForm([['id' => 'team', 'subject' => '<p>Van <span data-type="mergeTag" data-id="naam"></span></p>', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test']]]);
    FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Jan', 'email' => 'jan@example.test']]);

    editNotifications($form)
        ->callAction(onNotifications('edit', ['item' => 'team', 'test' => true]))
        ->assertNotified();

    Mail::assertSent(FormSubmissionCreatedMail::class, fn (FormSubmissionCreatedMail $mail): bool => $mail->hasTo('editor@example.test')
        && $mail->emailSubject === 'Van Jan');
    expect(FormSubmissionNotificationLog::count())->toBe(0);
});

it('shows on a card how often its notification went out', function () {
    $form = notifiedForm([['id' => 'team', 'subject' => 'Nieuw', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test']]]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Jan']]);

    foreach (['sent', 'sent', 'failed'] as $status) {
        FormSubmissionNotificationLog::create([
            'form_submission_id' => $submission->id,
            'notification_id' => 'team',
            'notification_subject' => 'Nieuw',
            'recipient' => 'hr@example.test',
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
        ]);
    }

    editNotifications($form)
        ->assertSee('Sent 2 times')
        ->assertSee('Failed 1 time this week');
});

it('shows the cards without actions on the view page', function () {
    $form = notifiedForm([['id' => 'team', 'subject' => 'Nieuw', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test']]]);

    Livewire::test(ViewForm::class, ['record' => $form->getRouteKey()])
        ->assertSee('hr@example.test')
        ->assertDontSee('Add notification');
});
