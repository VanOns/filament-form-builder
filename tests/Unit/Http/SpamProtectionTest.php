<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Forms\CustomForm;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormFromCode extends CustomForm
{
    public function hasHoneypot(): bool
    {
        return false;
    }
}

class FailingOnceForm extends CustomForm
{
    public static int $failures = 1;

    public function beforeStore(array $data): array
    {
        if (static::$failures-- > 0) {
            throw new RuntimeException('The database is away.');
        }

        return $data;
    }
}

beforeEach(function () {
    config([
        'filament-form-builder.honeypot' => ['enabled' => true, 'field' => 'ffb_website', 'min_seconds' => 2],
        'filament-form-builder.duplicate_seconds' => 10,
    ]);
});

function guardedForm(string $template = 'custom'): Form
{
    return Form::create([
        'title' => 'Contact ' . $template,
        'template' => $template,
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'file_upload', 'label' => 'Bijlage', 'key' => 'bijlage'],
        ]],
        'submit_notifications' => [['type' => 'content', 'content' => '<p>Bedankt, {{ $naam }}!</p>']],
    ]);
}

/**
 * The traps as a page sets them, shown the given seconds ago.
 *
 * @return array<string, string>
 */
function traps(int $shownSecondsAgo = 5, string $decoy = ''): array
{
    return ['ffb_website' => $decoy, 'ffb_token' => Crypt::encryptString((string) now()->subSeconds($shownSecondsAgo)->getTimestamp())];
}

function send(Form $form, array $payload): TestResponse
{
    return test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), $payload);
}

it('sets the traps in the form on the site', function () {
    $form = guardedForm();
    Route::middleware('web')->get('contact/{form}', fn (Form $form) => Blade::render('<x-render-form :form="$form" />', ['form' => $form]));

    $this->get('contact/' . $form->id)
        ->assertSee('name="ffb_website" value="" tabindex="-1" autocomplete="off"', escape: false)
        ->assertSee('<input type="hidden" name="ffb_token"', escape: false);

    expect($form->getHoneypot())->toMatchArray(['field' => 'ffb_website', 'tokenField' => 'ffb_token'])
        ->and((int) Crypt::decryptString($form->getHoneypot()['token']))->toBe(now()->getTimestamp());
});

it('stores what a person sends', function () {
    $form = guardedForm();

    send($form, ['naam' => 'Jan', ...traps()])
        ->assertSessionHas('submit_notification_content', '<p>Bedankt, Jan!</p>');

    expect(FormSubmission::sole()->data)->toBe(['naam' => 'Jan']);
});

it('answers a bot as if it got through, but keeps nothing', function (array $traps) {
    Log::spy();
    $form = guardedForm();

    send($form, ['naam' => 'Jan', ...$traps])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('submit_notification_content', '<p>Bedankt, Jan!</p>');

    expect(FormSubmission::count())->toBe(0);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => str_contains($message, 'honeypot caught'));
})->with([
    'the hidden field filled in' => fn () => traps(decoy: 'https://spam.example'),
    'sent within a second' => fn () => traps(shownSecondsAgo: 1),
    'a token made up' => fn () => ['ffb_website' => '', 'ffb_token' => 'made-up'],
]);

it('turns away a form that comes without the traps, and says why in the log', function () {
    Log::spy();

    send(guardedForm(), ['naam' => 'Jan'])->assertSessionHasErrors('ffb_token');

    expect(FormSubmission::count())->toBe(0);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'without the honeypot fields'));
});

it('leaves the traps out where the config or the form type turns them off', function () {
    config(['filament-form-builder.types.code' => FormFromCode::class]);
    $fromCode = guardedForm('code');

    send($fromCode, ['naam' => 'Jan'])->assertSessionHasNoErrors();

    config(['filament-form-builder.honeypot.enabled' => false]);
    send(guardedForm(), ['naam' => 'Anna'])->assertSessionHasNoErrors();

    expect(FormSubmission::count())->toBe(2)
        ->and($fromCode->getHoneypot())->toBeNull();
});

it('keeps a field from taking the name of a trap', function () {
    expect(TextInputField::reservedKeys())->toContain('ffb_website', 'ffb_token', 'cf-turnstile-response');
});

it('stores a double click once and answers both', function () {
    $form = guardedForm();
    $payload = ['naam' => 'Jan', ...traps()];

    send($form, $payload)->assertSessionHas('submit_notification_content', '<p>Bedankt, Jan!</p>');
    send($form, $payload)->assertSessionHas('submit_notification_content', '<p>Bedankt, Jan!</p>');
    send($form, ['naam' => 'Anna', ...traps()]);

    expect(FormSubmission::count())->toBe(2);

    $this->travel(11)->seconds();
    send($form, $payload);

    expect(FormSubmission::count())->toBe(3);
});

it('tells a repeat by its files as well', function () {
    Storage::fake('local');
    $form = guardedForm();

    send($form, ['naam' => 'Jan', 'bijlage' => UploadedFile::fake()->create('cv.pdf', 10), ...traps()]);
    send($form, ['naam' => 'Jan', 'bijlage' => UploadedFile::fake()->create('cv.pdf', 20), ...traps()]);

    expect(FormSubmission::count())->toBe(2);
});

it('stores a repeat after all when the first one failed', function () {
    config(['filament-form-builder.types.failing' => FailingOnceForm::class]);
    $form = guardedForm('failing');
    $payload = ['naam' => 'Jan', ...traps()];

    send($form, $payload)->assertServerError();
    send($form, $payload)->assertSessionHasNoErrors();

    expect(FormSubmission::count())->toBe(1);
});
