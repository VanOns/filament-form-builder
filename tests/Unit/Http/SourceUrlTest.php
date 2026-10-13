<?php

use Illuminate\Support\Facades\Mail;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Helpers\UrlHelper;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

function postFrom(?string $referer): FormSubmission
{
    $form = Form::create([
        'title' => 'Referer ' . uniqid(),
        'template' => 'custom',
        'custom' => ['fields' => [['type' => 'text', 'label' => 'Naam', 'key' => 'naam']]],
    ]);

    test()->withHeaders(array_filter(['Referer' => $referer]))
        ->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['naam' => 'Jan']);

    return FormSubmission::query()->where('form_id', $form->id)->sole();
}

it('keeps the page a submission came from', function () {
    expect(postFrom('https://example.test/contact?utm_source=x')->source_url)->toBe('https://example.test/contact?utm_source=x');
});

it('drops a referer that is no http link', function (string $referer) {
    expect(postFrom($referer)->source_url)->toBeNull();
})->with([
    'javascript' => ['javascript:alert(document.domain)'],
    'javascript with slashes' => ['javascript://example.test/%0aalert(1)'],
    'data' => ['data:text/html,<script>alert(1)</script>'],
    'not a url' => ['not a url'],
]);

it('accepts only http links', function () {
    expect(UrlHelper::http('https://example.test'))->toBe('https://example.test')
        ->and(UrlHelper::http('HTTP://example.test'))->toBe('HTTP://example.test')
        ->and(UrlHelper::http('javascript:alert(1)'))->toBeNull()
        ->and(UrlHelper::http('https://example.test/' . str_repeat('a', UrlHelper::MAX_LENGTH)))->toBeNull()
        ->and(UrlHelper::http(null))->toBeNull();
});

it('links no stored javascript url in a mail', function () {
    Mail::fake();

    $form = Form::create(['title' => 'Mail link', 'template' => 'custom', 'custom' => ['fields' => [['type' => 'text', 'label' => 'Naam', 'key' => 'naam']]]]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Jan'], 'source_url' => 'javascript:alert(1)']);

    $notification = new EmailNotification($submission, ['content' => '<p>{{ $submitted_from }}</p>', 'to' => ['hr@example.test']]);

    expect($notification->content)->not->toContain('href="javascript');
});
