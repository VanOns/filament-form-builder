<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Models\Form;

beforeEach(fn () => view()->share('errors', new ViewErrorBag()));

function siteForm(string $title): Form
{
    return Form::create(['title' => $title, 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
    ]]]);
}

it('loads its script and stylesheet once, however many forms a page has', function () {
    $html = Blade::render('<x-render-form :form="$a" /><x-render-form :form="$b" />', ['a' => siteForm('Contact'), 'b' => siteForm('Nieuwsbrief')]);

    expect(substr_count($html, 'js/van-ons/filament-form-builder/form-builder.js'))->toBe(1)
        ->and(substr_count($html, 'css/van-ons/filament-form-builder/form-builder.css'))->toBe(1)
        ->and(substr_count($html, 'class="ffb-form"'))->toBe(2);
});

it('leaves the stylesheet out for a site that styles its forms itself', function () {
    config(['filament-form-builder.styles' => false]);

    expect(Blade::render('<x-render-form :form="$form" />', ['form' => siteForm('Contact')]))
        ->toContain('form-builder.js')
        ->not->toContain('form-builder.css');
});

it('keeps the value of a text area as it is', function () {
    expect((string) (new TextAreaField(['key' => 'bericht', 'label' => 'Bericht', 'defaultValue' => 'Hallo']))->render())
        ->toContain('>Hallo</textarea>');
});

it('shows an error with its field and marks the field invalid', function () {
    view()->share('errors', (new ViewErrorBag())->put('default', new MessageBag(['naam' => 'Vul je naam in.'])));

    $html = (string) (new TextInputField(['key' => 'naam', 'label' => 'Naam']))->render();

    expect($html)->toContain('aria-invalid="true"')
        ->and($html)->toMatch('#<p class="ffb-error">Vul je naam in.</p>\s*</div>\s*$#');
});

it('shows the thank-you message above the form after a submission', function () {
    session(['submit_notification_type' => 'content', 'submit_notification_content' => '<p>Bedankt Jan!</p>']);

    expect(Blade::render('<x-render-form :form="$form" />', ['form' => siteForm('Contact')]))
        ->toMatch('#<div class="ffb-message" role="status">\s*<p>Bedankt Jan!</p>\s*</div>#');
});

it('renders the editor\'s text and message without script', function () {
    $form = Form::create(['title' => 'Scripted', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'text_block', 'text' => '<p><strong>Welkom</strong> <a href="https://example.test">hier</a></p><img src="x" onerror="alert(1)"><script>alert(2)</script>'],
    ]]]);

    session(['submit_notification_type' => 'content', 'submit_notification_content' => '<p>Bedankt <em>Jan</em>!</p><img src="x" onerror="alert(3)"><a href="javascript:alert(4)">x</a>']);

    $html = Blade::render('<x-render-form :form="$form" />', ['form' => $form]);

    expect($html)->toContain('<strong>Welkom</strong>')
        ->toContain('href="https://example.test"')
        ->toContain('<em>Jan</em>')
        ->not->toContain('onerror')
        ->not->toContain('<script>alert')
        ->not->toContain('javascript:alert');
});
