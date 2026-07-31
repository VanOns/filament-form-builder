<?php

use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

it('belongs to a form', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => []]);

    expect($submission->form->id)->toBe($form->id);
});

it('is soft deleted and not returned in default queries', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => []]);
    $submission->delete();

    expect(FormSubmission::find($submission->id))->toBeNull()
        ->and(FormSubmission::withTrashed()->find($submission->id))->not->toBeNull();
});

it('getFormattedData returns scalar values unchanged', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['name' => 'Alice', 'message' => 'Hello'],
    ]);

    expect($submission->getFormattedData())->toBe(['name' => 'Alice', 'message' => 'Hello']);
});

it('getFormattedData flattens array values to a comma-separated string', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['interests' => ['php', 'laravel', 'filament']],
    ]);

    expect($submission->getFormattedData())->toBe(['interests' => 'php, laravel, filament']);
});

it('getFormattedData filters out empty/null values', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['name' => 'Alice', 'phone' => '', 'fax' => null],
    ]);

    expect($submission->getFormattedData())->toHaveKey('name')
        ->not->toHaveKey('phone')
        ->not->toHaveKey('fax');
});

it('getAllUrlsInData returns URLs from flat data', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => [
            'website' => 'https://example.com',
            'name' => 'Alice',
        ],
    ]);

    expect($submission->getAllUrlsInData())->toBe(['https://example.com']);
});

it('getAllUrlsInData finds URLs nested inside arrays', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => [
            'links' => ['http://foo.com', 'https://bar.com'],
        ],
    ]);

    expect($submission->getAllUrlsInData())->toBe(['http://foo.com', 'https://bar.com']);
});

it('getAllUrlsInData returns an empty array when no URLs are present', function () {
    $form = Form::create(['title' => 'Contact']);
    $submission = FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['name' => 'Alice', 'message' => 'Hello'],
    ]);

    expect($submission->getAllUrlsInData())->toBeEmpty();
});
