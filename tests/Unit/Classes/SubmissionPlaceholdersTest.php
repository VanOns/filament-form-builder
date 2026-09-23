<?php

use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

function placeholderSubmission(array $data = [], ?string $email = null): FormSubmission
{
    $form = Form::create(['title' => 'Contact']);

    return FormSubmission::create([
        'form_id' => $form->id,
        'submitter_email' => $email,
        'data' => $data,
    ]);
}

it('exposes the submitted values, the form title and the submitter', function () {
    $submission = placeholderSubmission(['key_naam' => 'Jesse'], 'jesse@example.test');

    expect(SubmissionPlaceholders::make($submission)->values())
        ->toMatchArray([
            'key_naam' => 'Jesse',
            'naam' => 'Jesse',
            'form_title' => 'Contact',
            'submitter_email' => 'jesse@example.test',
        ]);
});

it('lets a submitted field win from the form title', function () {
    $submission = placeholderSubmission(['form_title' => 'Eigen waarde']);

    expect(SubmissionPlaceholders::make($submission)->values()['form_title'])->toBe('Eigen waarde');
});

it('drops empty values so their placeholder stays recognisable', function () {
    $submission = placeholderSubmission(['key_naam' => '']);

    expect(SubmissionPlaceholders::make($submission)->values())->not->toHaveKey('key_naam');
});

it('flattens a multi-value field the way the mail does', function () {
    $submission = placeholderSubmission(['key_interesses' => ['php', 'laravel']]);

    expect(SubmissionPlaceholders::make($submission)->values()['key_interesses'])->toBe('php, laravel');
});

it('replaces both placeholder spellings and leaves unknown ones alone', function () {
    $submission = placeholderSubmission(['key_naam' => 'Jesse']);

    expect(SubmissionPlaceholders::make($submission)->replace('Hoi {{ $key_naam }} en {{$key_naam}}, {{ $key_x }}'))
        ->toBe('Hoi Jesse en Jesse, {{ $key_x }}');
});

it('does not treat a placeholder a visitor typed as a placeholder', function () {
    $submission = placeholderSubmission(['key_naam' => '{{ $submitter_email }}'], 'jesse@example.test');

    expect(SubmissionPlaceholders::make($submission)->replace('Hoi {{ $key_naam }}'))
        ->toBe('Hoi {{ $submitter_email }}');
});

it('still replaces the placeholders in an e-mail notification', function () {
    $submission = placeholderSubmission(['key_naam' => 'Jesse'], 'jesse@example.test');

    $notification = new EmailNotification($submission, [
        'subject' => 'Inzending {{ $form_title }}',
        'content' => '<p>Hoi {{ $key_naam }}, we hebben je bericht ontvangen.</p>',
        'senderName' => '{{ $form_title }}',
        'receivers' => ['key_naam', 'info@example.test'],
    ]);

    expect($notification->subject)->toBe('Inzending Contact')
        ->and($notification->content)->toBe('<p>Hoi Jesse, we hebben je bericht ontvangen.</p>')
        ->and($notification->senderName)->toBe('Contact')
        // A receiver naming a field resolves to its value, anything else is used as typed.
        ->and($notification->receivers)->toBe(['Jesse', 'info@example.test']);
});

it('strips a placeholder the e-mail cannot fill in', function () {
    $submission = placeholderSubmission(['key_naam' => 'Jesse']);

    $notification = new EmailNotification($submission, [
        'subject' => 'Inzending',
        'content' => '<p>Hoi {{ $key_onbekend }}.</p>',
    ]);

    expect($notification->content)->toBe('<p>Hoi .</p>');
});
