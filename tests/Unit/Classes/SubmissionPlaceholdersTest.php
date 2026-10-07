<?php

use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

function placeholderSubmission(array $data = []): FormSubmission
{
    $form = Form::create(['title' => 'Contact']);

    return FormSubmission::create([
        'form_id' => $form->id,
        'data' => $data,
    ]);
}

it('exposes the submitted values and the form title', function () {
    $submission = placeholderSubmission(['naam' => 'Jesse']);

    expect(SubmissionPlaceholders::make($submission)->values())
        ->toMatchArray([
            'naam' => 'Jesse',
            'form_title' => 'Contact',
        ]);
});

it('lets a submitted field win from the form title', function () {
    $submission = placeholderSubmission(['form_title' => 'Eigen waarde']);

    expect(SubmissionPlaceholders::make($submission)->values()['form_title'])->toBe('Eigen waarde');
});

it('drops empty values so their placeholder stays recognisable', function () {
    $submission = placeholderSubmission(['naam' => '']);

    expect(SubmissionPlaceholders::make($submission)->values())->not->toHaveKey('naam');
});

it('flattens a multi-value field the way the mail does', function () {
    $submission = placeholderSubmission(['interesses' => ['php', 'laravel']]);

    expect(SubmissionPlaceholders::make($submission)->values()['interesses'])->toBe('php, laravel');
});

it('replaces both placeholder spellings and leaves unknown ones alone', function () {
    $submission = placeholderSubmission(['naam' => 'Jesse']);

    expect(SubmissionPlaceholders::make($submission)->replace('Hoi {{ $naam }} en {{$naam}}, {{ $x }}'))
        ->toBe('Hoi Jesse en Jesse, {{ $x }}');
});

it('does not treat a placeholder a visitor typed as a placeholder', function () {
    $submission = placeholderSubmission(['naam' => '{{ $form_title }}']);

    expect(SubmissionPlaceholders::make($submission)->replace('Hoi {{ $naam }}'))
        ->toBe('Hoi {{ $form_title }}');
});

it('still replaces the placeholders in an e-mail notification', function () {
    $submission = placeholderSubmission(['naam' => 'Jesse', 'email' => 'jesse@example.test']);

    $notification = new EmailNotification($submission, [
        'subject' => 'Inzending {{ $form_title }}',
        'content' => '<p>Hoi {{ $naam }}, we hebben je bericht ontvangen.</p>',
        'senderName' => '{{ $form_title }}',
        'receivers' => ['email', 'info@example.test', 'naam'],
    ]);

    expect($notification->subject)->toBe('Inzending Contact')
        ->and($notification->content)->toBe('<p>Hoi Jesse, we hebben je bericht ontvangen.</p>')
        ->and($notification->senderName)->toBe('Contact')
        // A receiver naming a field resolves to its answer; one that is no address is left out.
        ->and($notification->receivers)->toBe(['jesse@example.test', 'info@example.test']);
});

it('strips a placeholder the e-mail cannot fill in', function () {
    $submission = placeholderSubmission(['naam' => 'Jesse']);

    $notification = new EmailNotification($submission, [
        'subject' => 'Inzending',
        'content' => '<p>Hoi {{ $onbekend }}.</p>',
    ]);

    expect($notification->content)->toBe('<p>Hoi .</p>');
});

it('escapes an answer before it goes into the html of a notification', function () {
    $submission = placeholderSubmission(['naam' => '<a href="https://evil.test">Klik</a>']);

    $notification = new EmailNotification($submission, [
        'subject' => 'Van {{ $naam }}',
        'content' => '<p>Van {{ $naam }}</p>{{ $all_fields }}',
    ]);

    expect($notification->content)->not->toContain('<a href')
        ->and($notification->content)->toContain('&lt;a href=&quot;https://evil.test&quot;&gt;Klik&lt;/a&gt;');
});

it('does not read a placeholder typed into a field as one in the field overview', function () {
    $submission = placeholderSubmission(['naam' => '{{ $form_title }}']);

    $notification = new EmailNotification($submission, ['subject' => 'Nieuw', 'content' => '{{ $all_fields }}']);

    expect($notification->content)->toContain('<b>Naam</b>: {{ $form_title }}</p>');
});

it('points the placeholders of a renamed key at its new name', function () {
    $renamed = SubmissionPlaceholders::rename([
        'subject' => 'Van {{ $naam }} ({{$naam}})',
        'content' => ['<p>{{ $naam }} en {{ $naam_2 }}</p>'],
        'sender' => null,
    ], 'naam', 'volledige_naam');

    expect($renamed)->toBe([
        'subject' => 'Van {{ $volledige_naam }} ({{ $volledige_naam }})',
        'content' => ['<p>{{ $volledige_naam }} en {{ $naam_2 }}</p>'],
        'sender' => null,
    ]);
});

it('leaves a dollar sign in an answer alone', function () {
    $submission = placeholderSubmission(['bericht' => 'Kost het $100 of $USD?']);

    $notification = new EmailNotification($submission, ['subject' => 'Vraag', 'content' => '<p>{{ $bericht }}</p>']);

    expect($notification->content)->toBe('<p>Kost het $100 of $USD?</p>');
});

it('drops a placeholder no answer fills from a mail subject', function () {
    $notification = new EmailNotification(placeholderSubmission(), ['subject' => 'Vraag van {{ $onbekend }}', 'content' => '']);

    expect($notification->subject)->toBe('Vraag van');
});

it('fills in a key with a dash and placeholders spaced any way', function () {
    $submission = placeholderSubmission(['e-mailadres' => 'jan@example.com']);

    expect(SubmissionPlaceholders::make($submission)->replace('{{$e-mailadres}} / {{   $e-mailadres }}'))
        ->toBe('jan@example.com / jan@example.com');
});
