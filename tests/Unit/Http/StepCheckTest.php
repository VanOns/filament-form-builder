<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\SignUpForm;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

beforeEach(function () {
    config(['filament-form-builder.types.sign_up' => SignUpForm::class]);
});

function signUpForm(string $type = 'sign_up', array $fields = []): Form
{
    return Form::create([
        'title' => 'Aanmelden ' . Str::random(6),
        'template' => $type,
        'custom' => ['fields' => $fields],
        'submit_notifications' => [['type' => 'content', 'content' => '<p>Welkom!</p>']],
    ]);
}

function checkStep(Form $form, array $data, string $keys): TestResponse
{
    return test()->postJson(route('filament-form-builder.form.store', ['formId' => $form->id]), $data, [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => $keys,
    ]);
}

it('checks only the keys of the step, with the rules only the server knows, and stores nothing', function () {
    $form = signUpForm();

    checkStep($form, ['gebruikersnaam' => 'jan'], 'gebruikersnaam,gebruikersnaam.*')
        ->assertUnprocessable()
        ->assertHeader('Precognition', 'true')
        ->assertJsonValidationErrors(['gebruikersnaam' => 'Die naam is al bezet.'])
        ->assertJsonMissingValidationErrors(['email']);

    checkStep($form, ['gebruikersnaam' => 'piet'], 'gebruikersnaam,gebruikersnaam.*')
        ->assertNoContent()
        ->assertHeader('Precognition-Success', 'true');

    expect(FormSubmission::count())->toBe(0);
});

it('checks no step of a form type that does not ask for it', function () {
    $form = signUpForm('custom', [['type' => 'text', 'label' => 'Naam', 'key' => 'naam', 'required' => true]]);

    checkStep($form, [], 'naam')->assertForbidden();

    expect(FormSubmission::count())->toBe(0);
});

it('leaves the spam traps to the form itself', function () {
    config(['filament-form-builder.honeypot' => ['enabled' => true, 'field' => 'ffb_website', 'min_seconds' => 2]]);

    checkStep(signUpForm(), ['gebruikersnaam' => 'piet'], 'gebruikersnaam')->assertNoContent();
});

it('does not use up the submissions of the hour', function () {
    config(['filament-form-builder.rate_limit_per_hour' => 1]);
    $form = signUpForm();

    foreach (range(1, 3) as $attempt) {
        checkStep($form, ['gebruikersnaam' => 'piet'], 'gebruikersnaam')->assertNoContent();
    }

    test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['gebruikersnaam' => 'piet', 'email' => 'piet@example.test'])
        ->assertSessionHasNoErrors();

    expect(FormSubmission::count())->toBe(1);
});

it('has the page check the steps of such a form type on the server', function () {
    view()->share('errors', new ViewErrorBag());
    $render = fn (Form $form): string => Blade::render('<x-render-form :form="$form" />', ['form' => $form]);

    expect($render(signUpForm()))->toContain('data-form-builder-check-steps')
        ->and($render(signUpForm('custom', [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'step', 'title' => 'Verder'],
            ['type' => 'text', 'label' => 'Plaats', 'key' => 'plaats'],
        ])))->not->toContain('data-form-builder-check-steps');
});
