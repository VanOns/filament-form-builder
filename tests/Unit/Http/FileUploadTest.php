<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FileUploadField;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

beforeEach(function () {
    Storage::fake('local');
});

function uploadSubmission(array $payload, bool $multiple = false): FormSubmission
{
    $form = Form::create([
        'title' => 'Solliciteren',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv', 'multiple' => $multiple],
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

it('keeps every file of a multiple upload, posted as the browser names them', function () {
    $submission = uploadSubmission(['cv' => [pdf(), UploadedFile::fake()->create('brief.pdf', 10)]], multiple: true);
    $field = $submission->form->getFields()[1];

    expect(array_column($submission->files['cv'], 'name'))->toBe(['cv.pdf', 'brief.pdf'])
        ->and($field->getKey())->toBe('cv')
        ->and($field->getInputName())->toBe('cv[]');
});

it('keeps the size and type of an upload, so showing it needs no trip to the disk', function () {
    $submission = uploadSubmission(['cv' => pdf()]);
    Storage::disk('local')->put($submission->files['cv'][0]['path'], 'changed');
    $file = $submission->getFiles()['cv'][0];

    expect($file->size())->toBe(10 * 1024)
        ->and($file->mimeType())->toBe('application/pdf');
});

it('serves an upload to whoever holds its signed link', function () {
    $submission = uploadSubmission(['cv' => pdf()]);

    $response = test()->get($submission->getFiles()['cv'][0]->url());

    $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')->toContain('cv.pdf');
});

it('downloads a file that would open in the browser when asked to', function () {
    $file = uploadSubmission(['cv' => pdf()])->getFiles()['cv'][0];

    $response = test()->get($file->downloadUrl());

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($file->opensInBrowser())->toBeTrue()
        ->and($file->mimeType())->toBe('application/pdf')
        ->and($file->size())->toBeInt();
});

it('refuses a link that was not signed, was changed or has expired', function () {
    $url = uploadSubmission(['cv' => pdf()])->getFiles()['cv'][0]->url();

    test()->get(strtok($url, '?'))->assertForbidden();
    test()->get(str_replace('/files/cv/0', '/files/cv/1', $url))->assertForbidden();

    test()->travel(8)->days();
    test()->get($url)->assertForbidden();
});

it('downloads a file a browser could run script from, instead of opening it', function () {
    config(['filament-form-builder.uploads.extensions' => null]);

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

    // ...and a text field takes no array, so it never becomes a file either.
    $withText = Form::create([
        'title' => 'Naam en cv',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv'],
        ]],
    ]);

    test()->post(route('filament-form-builder.form.store', ['formId' => $withText->id]), ['naam' => $forged])
        ->assertSessionHasErrors('naam');

    expect(FormSubmission::query()->where('form_id', $withText->id)->exists())->toBeFalse();
});

// post() takes files out of the data, so a value and a file under one key need call().
it('never stores a file posted beside a typed value under the key of a text field', function () {
    $form = Form::create([
        'title' => 'Naam en cv',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv'],
        ]],
    ]);

    test()->call(
        'POST',
        route('filament-form-builder.form.store', ['formId' => $form->id]),
        ['naam' => 'Een naam'],
        files: ['naam' => UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>')],
    )->assertSessionHasErrors('naam');

    expect(FormSubmission::query()->where('form_id', $form->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('checks an upload that comes with an empty value under the same key', function (bool $multiple, string $errorKey) {
    $form = Form::create([
        'title' => 'Optioneel cv',
        'template' => 'custom',
        'custom' => ['fields' => [['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv', 'multiple' => $multiple]]],
    ]);
    $file = UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>');

    test()->call(
        'POST',
        route('filament-form-builder.form.store', ['formId' => $form->id]),
        ['cv' => ''],
        files: ['cv' => $multiple ? [$file] : $file],
    )->assertSessionHasErrors($errorKey);

    expect(FormSubmission::query()->where('form_id', $form->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'a single upload' => [false, 'cv'],
    'a multiple upload' => [true, 'cv.0'],
]);

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

it('takes only the file types the config allows, judged by their content', function () {
    $form = Form::create([
        'title' => 'Alleen documenten',
        'template' => 'custom',
        'custom' => ['fields' => [['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv']]],
    ]);
    $post = fn (UploadedFile $file) => test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['cv' => $file]);

    // A fake upload takes its type from its name; a real one, as here, from its content.
    $path = tempnam(sys_get_temp_dir(), 'ffb');
    file_put_contents($path, '<!doctype html><html><body><script>alert(1)</script></body></html>');

    $post(new UploadedFile($path, 'cv.pdf', 'application/pdf', null, true))
        ->assertSessionHasErrors('cv');
    $post(UploadedFile::fake()->createWithContent('cv.html', '<script>alert(1)</script>'))
        ->assertSessionHasErrors('cv');
    $post(UploadedFile::fake()->image('photo.png'))
        ->assertSessionHasNoErrors();

    expect(FormSubmission::query()->where('form_id', $form->id)->count())->toBe(1);
});

it('offers the allowed types in the file picker', function () {
    view()->share('errors', new ViewErrorBag());
    config(['filament-form-builder.uploads.extensions' => ['.PDF', 'png']]);

    expect((string) (new FileUploadField(['key' => 'cv', 'label' => 'CV']))->render())->toContain('accept=".pdf,.png"');

    config(['filament-form-builder.uploads.extensions' => null]);

    expect((string) (new FileUploadField(['key' => 'cv', 'label' => 'CV']))->render())->not->toContain('accept=');
});

it('leaves the size rule out when no size is set', function () {
    config(['filament-form-builder.uploads.max_size' => null, 'filament-form-builder.uploads.extensions' => null]);

    expect((new FileUploadField(['key' => 'cv']))->getRules()['cv'])->toBe(['nullable', 'file']);
});

function limitedUploadForm(array $settings): Form
{
    return Form::create([
        'title' => 'Bijlagen',
        'template' => 'custom',
        'custom' => ['fields' => [['type' => 'file_upload', 'label' => 'Bijlagen', 'key' => 'bijlagen', ...$settings]]],
    ]);
}

it('takes fewer file types when the field asks for fewer, and offers only those', function () {
    $form = limitedUploadForm(['extensions' => ['pdf']]);
    $post = fn (UploadedFile $file) => test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['bijlagen' => $file]);

    $post(UploadedFile::fake()->image('foto.png'))->assertSessionHasErrors('bijlagen');
    $post(pdf())->assertSessionHasNoErrors();

    expect((new FileUploadField(['extensions' => ['pdf']]))->getAccept())->toBe('.pdf')
        ->and(FormSubmission::query()->where('form_id', $form->id)->count())->toBe(1);
});

it('never takes a file type the config does not allow, whatever the field asks for', function () {
    config(['filament-form-builder.uploads.extensions' => ['pdf', 'png']]);

    expect((new FileUploadField(['extensions' => ['PDF', '.exe']]))->getExtensions())->toBe(['pdf'])
        ->and((new FileUploadField(['extensions' => ['exe']]))->getExtensions())->toBe(['pdf', 'png'])
        ->and((new FileUploadField())->getExtensions())->toBe(['pdf', 'png']);
});

it('takes at most as many files as the field asks for', function () {
    $form = limitedUploadForm(['multiple' => true, 'maxFiles' => 2]);
    $post = fn (array $files) => test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['bijlagen' => $files]);

    $post([pdf(), pdf(), pdf()])->assertSessionHasErrors(['bijlagen' => 'The Bijlagen field may have at most 2 files.']);
    $post([pdf(), pdf()])->assertSessionHasNoErrors();

    expect(FormSubmission::query()->where('form_id', $form->id)->sole()->files['bijlagen'])->toHaveCount(2);
});

it('caps the files of a multiple upload at what the config allows', function () {
    expect(FileUploadField::make('bijlagen')->multiple()->getMaxFiles())->toBe(10)
        ->and(FileUploadField::make('bijlagen')->multiple()->maxFiles(25)->getMaxFiles())->toBe(10);

    config(['filament-form-builder.uploads.max_files' => null]);

    expect(FileUploadField::make('bijlagen')->multiple()->getRules()['bijlagen'])->toBe(['nullable', 'array'])
        ->and(FileUploadField::make('bijlagen')->multiple()->maxFiles(25)->getMaxFiles())->toBe(25);
});

it('keeps a file under the size the field asks for, and never over the config', function () {
    config(['filament-form-builder.uploads.max_size' => 2048]);
    $form = limitedUploadForm(['maxSize' => 100]);
    $post = fn (int $kilobytes) => test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), [
        'bijlagen' => UploadedFile::fake()->create('cv.pdf', $kilobytes, 'application/pdf'),
    ]);

    $post(101)->assertSessionHasErrors('bijlagen');
    $post(100)->assertSessionHasNoErrors();

    expect((new FileUploadField(['maxSize' => '5000']))->getMaxSize())->toBe(2048)
        ->and((new FileUploadField(['maxSize' => '']))->getMaxSize())->toBe(2048);
});
