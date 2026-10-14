<?php

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use VanOns\FilamentFormBuilder\Models\Form;

function answeringForm(array $outcome): Form
{
    return Form::create([
        'title' => 'Contact ' . Str::random(6),
        'template' => 'custom',
        'custom' => ['fields' => [['type' => 'text', 'label' => 'Naam', 'key' => 'naam']]],
        'submit_notifications' => [$outcome],
    ]);
}

function sendFrom(Form $form, array $headers = []): TestResponse
{
    return test()->flushHeaders()
        ->from('http://localhost/contact')
        ->withHeaders($headers)
        ->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['naam' => 'Jan']);
}

it('sends the visitor back to the page with the message, which a browser and Inertia both follow', function (array $headers) {
    $form = answeringForm(['type' => 'content', 'content' => '<p>Bedankt!</p>']);

    sendFrom($form, $headers)
        ->assertStatus(303)
        ->assertRedirect('http://localhost/contact')
        ->assertSessionHas('submit_notification_content', '<p>Bedankt!</p>');
})->with([
    'a browser' => [[]],
    'Inertia' => [['X-Inertia' => 'true']],
]);

it('sends the visitor on to the page of the site the form redirects to', function () {
    $form = answeringForm(['type' => 'url', 'url' => 'http://localhost/bedankt']);

    sendFrom($form, ['X-Inertia' => 'true'])
        ->assertStatus(303)
        ->assertRedirect('http://localhost/bedankt');
});

it('has Inertia send the browser itself to another site', function () {
    $form = answeringForm(['type' => 'url', 'url' => 'https://example.com/bedankt']);

    sendFrom($form, ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://example.com/bedankt');

    sendFrom($form)
        ->assertStatus(303)
        ->assertRedirect('https://example.com/bedankt');
});
