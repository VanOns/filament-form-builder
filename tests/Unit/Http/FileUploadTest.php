<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

beforeEach(function () {
    Storage::fake('local');
});

function uploadSubmission(array $payload): FormSubmission
{
    $form = Form::create([
        'title' => 'Solliciteren',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv'],
        ]],
    ]);

    test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), $payload);

    return FormSubmission::query()->where('form_id', $form->id)->sole();
}

function pdf(): UploadedFile
{
    return UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf');
}

it('keeps an upload apart from the answers, under its original name', function () {
    $submission = uploadSubmission(['naam' => 'Jan', 'cv' => pdf()]);

    expect($submission->data)->toBe(['naam' => 'Jan'])
        ->and($submission->files['cv'][0]['name'])->toBe('cv.pdf');

    Storage::disk('local')->assertExists($submission->files['cv'][0]['path']);
});

it('serves an upload to whoever holds its signed link', function () {
    $submission = uploadSubmission(['cv' => pdf()]);

    $response = test()->get($submission->getFiles()['cv'][0]->url());

    $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')->toContain('cv.pdf');
});

it('refuses a link that was not signed, was changed or has expired', function () {
    $url = uploadSubmission(['cv' => pdf()])->getFiles()['cv'][0]->url();

    test()->get(strtok($url, '?'))->assertForbidden();
    test()->get(str_replace('/files/cv/0', '/files/cv/1', $url))->assertForbidden();

    test()->travel(8)->days();
    test()->get($url)->assertForbidden();
});

it('downloads a file a browser could run script from, instead of opening it', function () {
    $submission = uploadSubmission(['cv' => UploadedFile::fake()->createWithContent('cv.html', '<script>alert(1)</script>')]);

    $response = test()->get($submission->getFiles()['cv'][0]->url());

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('never takes a typed value for a stored file', function () {
    $forged = ['path' => '../.env', 'name' => 'cv.pdf'];

    $uploadOnly = Form::create([
        'title' => 'Alleen een cv',
        'template' => 'custom',
        'custom' => ['fields' => [['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv']]],
    ]);

    // The upload field itself only accepts a real file...
    test()->post(route('filament-form-builder.form.store', ['formId' => $uploadOnly->id]), ['cv' => $forged])
        ->assertSessionHasErrors('cv');

    // ...and what lands in a text field stays an answer, never a file.
    $submission = uploadSubmission(['naam' => $forged]);

    expect($submission->files)->toBeNull()
        ->and($submission->getFiles())->toBe([]);
});

it('keeps the files of a submission that can still be restored', function () {
    $submission = uploadSubmission(['cv' => pdf()]);
    $path = $submission->files['cv'][0]['path'];

    $submission->delete();

    Storage::disk('local')->assertExists($path);
});

it('deletes the files of a submission that is deleted for good', function () {
    $submission = uploadSubmission(['cv' => pdf()]);
    $path = $submission->files['cv'][0]['path'];

    $submission->forceDelete();

    Storage::disk('local')->assertMissing($path);
});

it('deletes the files of every submission when its form is deleted for good', function () {
    $submission = uploadSubmission(['cv' => pdf()]);
    $path = $submission->files['cv'][0]['path'];
    $submission->delete();

    $submission->form->forceDelete();

    Storage::disk('local')->assertMissing($path);
});
