<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\NewsletterIntegration;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Enums\IntegrationStatus;
use VanOns\FilamentFormBuilder\Jobs\RunFormIntegrationJob;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionIntegrationLog;

class BrokenIntegration extends Integration
{
    public function handle(): void
    {
        throw new RuntimeException('The other side timed out.');
    }
}

class V2HookIntegration extends Integration
{
    public function handle(): void
    {
        try {
            $response = Http::post($this->integration['endpoint'], ['fields' => $this->formSubmission->data]);

            $this->setResponse($response->json())->setSuccess($response->successful());
        } catch (Exception $exception) {
            $this->setResponse($exception->getMessage())->setSuccess(false);
        }
    }
}

beforeEach(function () {
    NewsletterIntegration::$sent = [];
    config(['filament-form-builder.integrations' => [NewsletterIntegration::class, BrokenIntegration::class, V2HookIntegration::class]]);
});

/**
 * @param  list<array<string, mixed>>  $more
 */
function newsletterForm(array $newsletter = [], array $more = []): Form
{
    return Form::create([
        'title' => 'Contact',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
            ['type' => 'email', 'label' => 'E-mailadres', 'key' => 'email'],
            ['type' => 'checkbox', 'label' => 'Nieuwsbrief', 'key' => 'nieuwsbrief'],
        ]],
        'submit_notifications' => [['type' => 'content', 'content' => '<p>Bedankt!</p>']],
        'integrations' => [[
            'id' => 'news',
            'class' => NewsletterIntegration::class,
            'list' => 'Klanten',
            'api_key' => Crypt::encryptString('secret-key'),
            'mapping' => ['email' => 'email', 'first_name' => 'voornaam', 'note' => '<p>Via <span data-type="mergeTag" data-id="form_title"></span></p>'],
            ...$newsletter,
        ], ...$more],
    ]);
}

function sendNewsletterForm(Form $form, array $data): FormSubmission
{
    test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), $data)->assertSessionHasNoErrors();

    return FormSubmission::query()->where('form_id', $form->id)->latest('id')->firstOrFail();
}

it('queues an integration that is on, and logs one its conditions leave out', function () {
    Queue::fake();
    $form = newsletterForm(
        ['conditions' => [['key' => 'nieuwsbrief', 'operator' => 'not_empty']]],
        [['id' => 'off', 'class' => BrokenIntegration::class, 'enabled' => false]],
    );

    $left = sendNewsletterForm($form, ['voornaam' => 'Jan', 'email' => 'jan@example.test']);
    $sent = sendNewsletterForm($form, ['voornaam' => 'Jan', 'email' => 'jan@example.test', 'nieuwsbrief' => '1']);

    expect($left->integrationLogs()->pluck('status')->all())->toBe([IntegrationStatus::Skipped])
        ->and($sent->integrationLogs()->sole())
        ->integration_id->toBe('news')
        ->status->toBe(IntegrationStatus::Queued);
    Queue::assertPushed(RunFormIntegrationJob::class, fn (RunFormIntegrationJob $job): bool => $job->logId === $sent->integrationLogs()->sole()->id);
    Queue::assertPushed(RunFormIntegrationJob::class, 1);
});

it('hands an integration the answers it maps and its decrypted settings, and logs how it went', function () {
    $submission = sendNewsletterForm(newsletterForm(), ['voornaam' => 'Jan', 'email' => 'jan@example.test']);

    expect(NewsletterIntegration::$sent)->toBe([[
        'list' => 'Klanten',
        'api_key' => 'secret-key',
        'email' => 'jan@example.test',
        'first_name' => 'Jan',
        'note' => 'Via Contact',
    ]])
        ->and($submission->integrationLogs()->sole())
        ->status->toBe(IntegrationStatus::Succeeded)
        ->response->toBe(['subscribed' => 'jan@example.test'])
        ->attempts->toBe(1)
        ->ran_at->not->toBeNull();
});

it('fails for good when the integration says so', function () {
    $submission = sendNewsletterForm(newsletterForm(), ['voornaam' => 'Jan', 'email' => 'taken@example.test']);

    expect($submission->integrationLogs()->sole())
        ->status->toBe(IntegrationStatus::Failed)
        ->error->toBe('Already on the list.')
        ->response->toBe(['code' => 'member_exists']);
});

it('logs an integration that throws as failed, without the visitor noticing', function () {
    $form = newsletterForm(['enabled' => false], [['id' => 'broken', 'class' => BrokenIntegration::class]]);

    $submission = sendNewsletterForm($form, ['voornaam' => 'Jan', 'email' => 'jan@example.test']);

    expect($submission->integrationLogs()->sole())
        ->integration_id->toBe('broken')
        ->status->toBe(IntegrationStatus::Failed)
        ->error->toBe('The other side timed out.');
});

it('fails an integration that was taken off the form while it waited', function () {
    $submission = FormSubmission::create(['form_id' => newsletterForm()->id, 'data' => []]);
    $log = $submission->integrationLogs()->create(['integration_id' => 'gone', 'integration' => NewsletterIntegration::class]);

    (new RunFormIntegrationJob($log->id))->handle();

    expect(FormSubmissionIntegrationLog::find($log->id))
        ->status->toBe(IntegrationStatus::Failed)
        ->error->toBe('This integration was taken off the form.');
});

it('runs an integration stored before it had an id by its place in the list', function () {
    $form = newsletterForm(['id' => null]);

    $submission = sendNewsletterForm($form, ['voornaam' => 'Jan', 'email' => 'jan@example.test']);

    expect($submission->integrationLogs()->sole())
        ->integration_id->toBe('0')
        ->status->toBe(IntegrationStatus::Succeeded);
});

it('maps the answers as shown, or as stored', function () {
    $form = newsletterForm(['mapping' => ['email' => 'email', 'first_name' => 'nieuwsbrief']]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['email' => 'jan@example.test', 'nieuwsbrief' => '1']]);
    $integration = Integration::fromArray($submission, $form->getIntegrations()['news']);

    expect($integration->mapped())->toBe(['email' => 'jan@example.test', 'first_name' => 'Yes', 'note' => null])
        ->and($integration->mapped(raw: true)['first_name'])->toBe('1');
});

it('runs an integration written for v2', function () {
    Http::fake([
        'hooks.example.test/up' => Http::response(['ok' => true]),
        'hooks.example.test/down' => fn () => throw new ConnectionException('Could not resolve host'),
    ]);
    $form = newsletterForm(['enabled' => false], [
        ['id' => 'up', 'class' => V2HookIntegration::class, 'endpoint' => 'https://hooks.example.test/up'],
        ['id' => 'down', 'class' => V2HookIntegration::class, 'endpoint' => 'https://hooks.example.test/down'],
    ]);

    $submission = sendNewsletterForm($form, ['voornaam' => 'Jan', 'email' => 'jan@example.test']);
    $logs = $submission->integrationLogs()->get()->keyBy('integration_id');

    expect($logs['up'])->status->toBe(IntegrationStatus::Succeeded)->response->toBe(['ok' => true])
        ->and($logs['down'])->status->toBe(IntegrationStatus::Failed)->error->toBe('Could not resolve host');
});
