<?php

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use VanOns\FilamentFormBuilder\Integrations\WebhookIntegration;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

function webhookFor(array $settings): WebhookIntegration
{
    $form = Form::create(['title' => 'Contact', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
    ]]]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['voornaam' => 'Jan'], 'source_url' => 'https://example.test/contact']);

    return new WebhookIntegration($submission, ['url' => 'https://hooks.example.test/forms', ...$settings]);
}

it('posts the submission as JSON', function () {
    Http::fake(['hooks.example.test/*' => Http::response(['received' => true])]);
    $webhook = webhookFor([]);

    $webhook->handle();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://hooks.example.test/forms'
        && $request['form']['title'] === 'Contact'
        && $request['submission']['source_url'] === 'https://example.test/contact'
        && $request['data'] === ['voornaam' => 'Jan']
        && $request['labels'] === ['voornaam' => 'Voornaam']
        && !$request->hasHeader('Authorization'));
    expect($webhook->success)->toBeTrue()
        ->and($webhook->response)->toBe(['status' => 200, 'received' => true]);
});

it('signs in the way it is set to, with the secrets decrypted', function (array $settings, string $secret, Closure $check) {
    Http::fake();

    webhookFor([...$settings, $secret => Crypt::encryptString($settings[$secret])])->handle();

    Http::assertSent($check);
})->with([
    'token' => [['auth' => 'bearer', 'token' => 'abc'], 'token', fn (Request $request): bool => $request->header('Authorization') === ['Bearer abc']],
    'username and password' => [['auth' => 'basic', 'username' => 'jan', 'password' => 'geheim'], 'password', fn (Request $request): bool => $request->header('Authorization') === ['Basic ' . base64_encode('jan:geheim')]],
    'own header' => [['auth' => 'header', 'header' => 'X-Api-Key', 'header_value' => 'k3y'], 'header_value', fn (Request $request): bool => $request->header('X-Api-Key') === ['k3y']],
]);

it('fails for good on an answer the other side will not change its mind about', function () {
    Http::fake(['*' => Http::response(['error' => 'Unknown form'], 404)]);
    $webhook = webhookFor([]);

    $webhook->handle();

    expect($webhook->success)->toBeFalse()
        ->and($webhook->error)->toBe('The URL answered with 404.')
        ->and($webhook->response)->toBe(['status' => 404, 'error' => 'Unknown form']);
});

it('leaves a server error to the queue to try again', function () {
    Http::fake(['*' => Http::response('Bad gateway', 502)]);

    webhookFor([])->handle();
})->throws(RequestException::class);

it('shows where it sends to on its card', function () {
    expect(WebhookIntegration::summary(['url' => 'https://hooks.example.test/forms/']))->toBe('hooks.example.test/forms')
        ->and(WebhookIntegration::summary([]))->toBeNull();
});

it('posts nowhere inside the server\'s own network', function (string $url) {
    config(['filament-form-builder.webhook.allow_private_network' => false]);
    Http::fake();
    $webhook = webhookFor(['url' => $url]);

    $webhook->handle();

    Http::assertNothingSent();
    expect($webhook->success)->toBeFalse()
        ->and($webhook->error)->toBe(__('filament-form-builder::general.webhook.blocked'));
})->with([
    'localhost' => ['http://localhost/hook'],
    'loopback' => ['http://127.0.0.1:8080/hook'],
    'private' => ['https://10.0.0.5/hook'],
    'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
    'ipv6 loopback' => ['http://[::1]/hook'],
    'no http' => ['file:///etc/passwd'],
]);

it('reaches a private network when the app allows it', function () {
    config(['filament-form-builder.webhook.allow_private_network' => true]);
    Http::fake();

    webhookFor(['url' => 'http://127.0.0.1:5678/webhook/form'])->handle();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:5678/webhook/form');
});

it('posts to a public address', function () {
    config(['filament-form-builder.webhook.allow_private_network' => false]);
    Http::fake();

    webhookFor(['url' => 'https://93.184.216.34/hook'])->handle();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://93.184.216.34/hook');
});
