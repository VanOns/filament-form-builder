<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use VanOns\FilamentFormBuilder\Models\Form;

function expiredSubmission(string $uri, array $headers = []): mixed
{
    $request = Request::create($uri, 'POST', ['naam' => 'Jan', '_token' => 'verlopen'], server: $headers);
    $request->setRouteResolver(fn () => rescue(fn () => app('router')->getRoutes()->match($request), report: false));
    $request->setLaravelSession(app('session.store'));

    return app(ExceptionHandler::class)->render($request, new TokenMismatchException());
}

function expiringForm(): Form
{
    return Form::create(['title' => 'Contact ' . Str::random(6), 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
    ]]]);
}

it('sends a form whose session expired back with its answers instead of a 419 page', function () {
    $form = expiringForm();

    $response = expiredSubmission(route('filament-form-builder.form.store', ['formId' => $form->id]));

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and(session('filament_form_builder_expired'))->toBe($form->id)
        ->and(session('_old_input'))->toBe(['naam' => 'Jan']);
});

it('says why the form was not sent, above the form it is about', function () {
    view()->share('errors', new ViewErrorBag());
    $form = expiringForm();
    session(['filament_form_builder_expired' => $form->id]);

    expect(Blade::render('<x-render-form :form="$form" />', ['form' => $form]))
        ->toContain('role="alert" tabindex="-1" data-form-builder-message')
        ->toContain('Your session had expired');

    expect(Blade::render('<x-render-form :form="$form" />', ['form' => expiringForm()]))
        ->not->toContain('Your session had expired');
});

it('leaves a 419 alone on any other route or for a request that wants JSON', function () {
    $form = expiringForm();

    expect(expiredSubmission('/elders')->getStatusCode())->toBe(419)
        ->and(expiredSubmission(route('filament-form-builder.form.store', ['formId' => $form->id]), ['HTTP_ACCEPT' => 'application/json'])->getStatusCode())->toBe(419);
});
