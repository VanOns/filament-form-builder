<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\FormCanvas;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

function turnstileForm(): Form
{
    config(['filament-form-builder.turnstile' => ['enabled' => true, 'key' => 'site-key', 'secret' => 'secret-key']]);

    return Form::create([
        'title' => 'Contact',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'turnstile'],
        ]],
    ]);
}

it('offers Turnstile in the palette only once it is set up', function () {
    $offered = fn (): bool => collect(FormCanvas::make('custom')->getPaletteGroups())->contains(fn (array $group): bool => isset($group['turnstile']));

    config(['filament-form-builder.turnstile' => ['enabled' => true, 'key' => '', 'secret' => 'secret']]);
    expect($offered())->toBeFalse();

    config(['filament-form-builder.turnstile' => ['enabled' => true, 'key' => 'key', 'secret' => 'secret']]);
    expect($offered())->toBeTrue();
});

it('shows the Cloudflare widget with the site key', function () {
    $form = turnstileForm();
    Route::middleware('web')->get('contact/{form}', fn (Form $form) => Blade::render('<x-render-form :form="$form" />', ['form' => $form]));

    $this->get('contact/' . $form->id)
        ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', escape: false)
        ->assertSee('class="cf-turnstile" data-sitekey="site-key"', escape: false);
});

it('stores a submission Cloudflare lets through, and keeps none of its token', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    $form = turnstileForm();

    $this->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['naam' => 'Jan', 'cf-turnstile-response' => 'token'])
        ->assertSessionHasNoErrors();

    expect(FormSubmission::sole()->data)->toBe(['naam' => 'Jan']);
    Http::assertSent(fn (Request $request): bool => $request['secret'] === 'secret-key' && $request['response'] === 'token' && $request['remoteip'] === '127.0.0.1');
});

it('turns away what Cloudflare does not let through, or what comes without a token', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
    $form = turnstileForm();
    $store = route('filament-form-builder.form.store', ['formId' => $form->id]);

    $this->post($store, ['naam' => 'Jan', 'cf-turnstile-response' => 'token'])
        ->assertSessionHasErrors(['cf-turnstile-response' => 'The Cloudflare Turnstile check failed.']);
    $this->post($store, ['naam' => 'Jan'])->assertSessionHasErrors('cf-turnstile-response');

    expect(FormSubmission::count())->toBe(0);
});
