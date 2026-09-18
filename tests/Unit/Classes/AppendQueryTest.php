<?php

use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

function queryFor(array $data = [], string $title = 'Bel me terug'): SubmissionPlaceholders
{
    $form = Form::create(['title' => $title]);

    return SubmissionPlaceholders::make(
        FormSubmission::create(['form_id' => $form->id, 'data' => $data])
    );
}

it('fills the placeholders with the submitted values', function () {
    $url = queryFor(['key_vestiging' => 'Alkmaar Centrum'])
        ->appendQuery('https://example.com/bedankt/', 'vestiging={{ $key_vestiging }}&form={{ $form_title }}');

    expect($url)->toBe('https://example.com/bedankt/?vestiging=Alkmaar%20Centrum&form=Bel%20me%20terug');
});

it('accepts placeholders without the key prefix and without spaces', function () {
    expect(queryFor(['key_naam' => 'Jesse'])->appendQuery('https://example.com/', 'a={{ $naam }}&b={{$key_naam}}'))
        ->toBe('https://example.com/?a=Jesse&b=Jesse');
});

it('encodes values so they cannot break the url', function () {
    expect(queryFor(['key_note' => 'a&b=c d/e?f'])->appendQuery('https://example.com/', 'note={{ $key_note }}'))
        ->toBe('https://example.com/?note=a%26b%3Dc%20d%2Fe%3Ff');
});

it('leaves a parameter empty when the field was not filled in', function () {
    expect(queryFor(['key_naam' => 'Jesse'])->appendQuery('https://example.com/', 'naam={{ $key_naam }}&leeg={{ $key_onbekend }}'))
        ->toBe('https://example.com/?naam=Jesse&leeg=');
});

it('leaves the url alone when there is nothing to append', function () {
    $placeholders = queryFor(['key_naam' => 'Jesse']);

    expect($placeholders->appendQuery('https://example.com/bedankt/', null))->toBe('https://example.com/bedankt/')
        ->and($placeholders->appendQuery('https://example.com/bedankt/', '   '))->toBe('https://example.com/bedankt/')
        // Only unresolved placeholders: nothing worth adding.
        ->and($placeholders->appendQuery('https://example.com/bedankt/', '{{ $key_onbekend }}'))->toBe('https://example.com/bedankt/')
        ->and($placeholders->appendQuery(null, 'naam={{ $key_naam }}'))->toBeNull();
});

it('keeps a query string the url already carries', function () {
    expect(queryFor(['key_naam' => 'Jesse'])->appendQuery('https://example.com/bedankt/?bron=mail', 'naam={{ $key_naam }}'))
        ->toBe('https://example.com/bedankt/?bron=mail&naam=Jesse');
});

it('keeps the fragment at the end where the browser expects it', function () {
    expect(queryFor(['key_naam' => 'Jesse'])->appendQuery('https://example.com/bedankt/#formulier', 'naam={{ $key_naam }}'))
        ->toBe('https://example.com/bedankt/?naam=Jesse#formulier');
});
