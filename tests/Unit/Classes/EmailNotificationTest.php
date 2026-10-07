<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Enums\NotificationStatus;
use VanOns\FilamentFormBuilder\Jobs\SendFormNotificationJob;
use VanOns\FilamentFormBuilder\Mail\FormSubmission\FormSubmissionCreatedMail;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

function mailForm(array $notifications = []): Form
{
    return Form::create([
        'title' => 'Solliciteren',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'email', 'label' => 'E-mailadres', 'key' => 'email'],
            ['type' => 'radio', 'label' => 'Vestiging', 'key' => 'vestiging', 'options' => [
                ['value' => 'utrecht', 'label' => 'Utrecht'],
                ['value' => 'zwolle', 'label' => 'Zwolle'],
            ]],
            ['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv'],
        ]],
        'notifications' => $notifications,
    ]);
}

function mailSubmission(Form $form, array $data = [], array $files = []): FormSubmission
{
    return FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['naam' => 'Jan', 'email' => 'jan@example.test', 'vestiging' => 'utrecht', ...$data],
        'files' => $files ?: null,
    ]);
}

it('fills in what a notification leaves out', function () {
    expect(EmailNotification::normalize(['subject' => 'Hoi', 'to' => ['field:email', ' hr@example.test ', '', ['email' => 'naam']]]))
        ->toMatchArray([
            'id' => null,
            'enabled' => true,
            'to' => ['field:email', 'hr@example.test'],
            'reply_to' => null,
            'conditions' => [],
            'conditionMatch' => 'all',
            'attach_files' => false,
        ]);
});

it('sends to the answer of a field, replies to another and leaves out what is no address', function () {
    $submission = mailSubmission(mailForm());

    $notification = new EmailNotification($submission, [
        'to' => ['field:email', 'hr@example.test', 'field:naam'],
        'reply_to' => 'field:email',
    ]);

    expect($notification->receivers)->toBe(['jan@example.test', 'hr@example.test'])
        ->and($notification->replyTo)->toBe('jan@example.test');
});

it('sends nothing when switched off, when its conditions fail or without recipients', function () {
    $submission = mailSubmission(mailForm());
    $mail = fn (array $settings): bool => (new EmailNotification($submission, ['to' => ['hr@example.test'], ...$settings]))->shouldSend();

    expect($mail([]))->toBeTrue()
        ->and($mail(['enabled' => false]))->toBeFalse()
        ->and($mail(['conditions' => [['key' => 'vestiging', 'operator' => 'equals', 'value' => 'zwolle']]]))->toBeFalse()
        ->and($mail(['conditions' => [['key' => 'vestiging', 'operator' => 'equals', 'value' => 'utrecht']]]))->toBeTrue()
        ->and($mail(['to' => ['field:naam']]))->toBeFalse();
});

it('sends the copies along with the mail of every recipient, but not to whoever it is already for', function () {
    Queue::fake();

    $form = mailForm([
        ['id' => 'team', 'subject' => 'Nieuw', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test', 'field:email'], 'cc' => ['baas@example.test', 'hr@example.test'], 'bcc' => ['field:email']],
    ]);

    $form->getType()->sendNotifications(mailSubmission($form));

    $copies = [];
    Queue::assertPushed(SendFormNotificationJob::class, function (SendFormNotificationJob $job) use (&$copies): bool {
        $copies[$job->receiver] = ['cc' => $job->cc, 'bcc' => $job->bcc];

        return true;
    });

    expect($copies)->toBe([
        'hr@example.test' => ['cc' => ['baas@example.test'], 'bcc' => ['jan@example.test']],
        'jan@example.test' => ['cc' => ['baas@example.test', 'hr@example.test'], 'bcc' => []],
    ]);
});

it('attaches the uploads that fit within the limit', function () {
    Storage::fake('local');
    Storage::disk('local')->put('form_uploads/cv.pdf', str_repeat('a', 6 * 1024));
    Storage::disk('local')->put('form_uploads/portfolio.zip', str_repeat('a', 6 * 1024));
    config(['filament-form-builder.uploads.attach_max_size' => 10]);

    $submission = mailSubmission(mailForm(), files: ['cv' => [
        ['path' => 'form_uploads/cv.pdf', 'name' => 'cv.pdf'],
        ['path' => 'form_uploads/portfolio.zip', 'name' => 'portfolio.zip'],
    ]]);

    $attached = fn (array $settings): array => array_map(
        fn ($file): string => $file->name,
        (new EmailNotification($submission, ['to' => ['hr@example.test'], ...$settings]))->attachments,
    );

    expect($attached(['attach_files' => true]))->toBe(['cv.pdf'])
        ->and($attached([]))->toBe([]);
});

it('queues a mail per recipient and logs which notification it came from', function () {
    Queue::fake();

    $form = mailForm([
        ['id' => 'team', 'subject' => 'Nieuw', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test', 'field:email'], 'reply_to' => 'field:email'],
        ['id' => 'off', 'enabled' => false, 'subject' => 'Uit', 'content' => '<p>Uit</p>', 'to' => ['hr@example.test']],
    ]);
    $submission = mailSubmission($form);

    $form->getType()->sendNotifications($submission);

    Queue::assertPushed(SendFormNotificationJob::class, 2);
    Queue::assertPushed(SendFormNotificationJob::class, fn (SendFormNotificationJob $job): bool => $job->mail->replyToAddress === 'jan@example.test');

    expect(FormSubmissionNotificationLog::pluck('notification_id')->all())->toBe(['team', 'team'])
        ->and(FormSubmissionNotificationLog::pluck('recipient')->all())->toBe(['hr@example.test', 'jan@example.test']);
});

it('sends the mail it queued, also after the trip through the queue, and logs it as sent', function () {
    Queue::fake();
    Mail::fake();

    $form = mailForm([['id' => 'team', 'subject' => 'Nieuw van {{ $naam }}', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test'], 'cc' => ['baas@example.test']]]);
    $form->getType()->sendNotifications(mailSubmission($form));

    unserialize(serialize(Queue::pushed(SendFormNotificationJob::class)->sole()))->handle();

    Mail::assertSent(FormSubmissionCreatedMail::class, fn (FormSubmissionCreatedMail $mail): bool => $mail->hasTo('hr@example.test')
        && $mail->hasCc('baas@example.test')
        && $mail->emailSubject === 'Nieuw van Jan');

    expect(FormSubmissionNotificationLog::sole()->status)->toBe(NotificationStatus::Sent);
});
