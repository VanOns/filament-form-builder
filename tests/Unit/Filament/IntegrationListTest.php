<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Fixtures\NewsletterIntegration;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\FormCanvas;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Integrations\WebhookIntegration;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionIntegrationLog;

beforeEach(function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
    config(['filament-form-builder.integrations' => [NewsletterIntegration::class, WebhookIntegration::class]]);
});

function integratedForm(array $integrations = []): Form
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
        'integrations' => $integrations,
    ]);
}

function onIntegrations(string $action, array $arguments = []): TestAction
{
    return TestAction::make($action)->schemaComponent('integrations', schema: 'form')->arguments($arguments);
}

function editIntegrations(Form $form)
{
    return Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
}

it('adds an integration by its type, with the fields it asks for found by name, and saves it straight away', function () {
    $form = integratedForm();

    editIntegrations($form)
        ->assertSee(['Add integration', 'Newsletter', 'Webhook'])
        ->mountAction(onIntegrations('add', ['class' => NewsletterIntegration::class]))
        ->assertSchemaStateSet(['mapping.email' => 'email', 'mapping.first_name' => 'voornaam', 'when' => 'always'])
        ->fillForm(['list' => 'Klanten', 'api_key' => 'secret-key'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertSee('List Klanten');

    $integration = $form->refresh()->integrations[0];

    expect($integration)
        ->id->toBeString()->not->toBeEmpty()
        ->class->toBe(NewsletterIntegration::class)
        ->enabled->toBeTrue()
        ->list->toBe('Klanten')
        ->mapping->toBe(['email' => 'email', 'first_name' => 'voornaam'])
        ->conditions->toBe([])
        ->and(Crypt::decryptString($integration['api_key']))->toBe('secret-key')
        ->and($integration)->not->toHaveKey('when');
});

it('starts a new integration from the defaults of its fields', function () {
    editIntegrations(integratedForm())
        ->mountAction(onIntegrations('add', ['class' => WebhookIntegration::class]))
        ->assertSchemaStateSet(['auth' => 'none', 'when' => 'always']);
});

it('only adds a type the config registers', function () {
    $form = integratedForm();

    editIntegrations($form)->callAction(onIntegrations('add', ['class' => FormSubmission::class]));

    expect($form->refresh()->integrations)->toBeEmpty();
});

it('keeps conditions only while it runs for some answers', function () {
    $form = integratedForm([['id' => 'news', 'class' => NewsletterIntegration::class, 'list' => 'Klanten', 'mapping' => ['email' => 'email']]]);
    $page = editIntegrations($form);

    $page->callAction(onIntegrations('edit', ['item' => 'news']), data: [
        'when' => 'conditions',
        'conditions' => [['key' => 'nieuwsbrief', 'operator' => 'not_empty']],
    ])->assertHasNoActionErrors()->assertSee('If Nieuwsbrief is ticked');

    expect($form->refresh()->integrations[0]['conditions'][0]['key'])->toBe('nieuwsbrief');

    $page->callAction(onIntegrations('edit', ['item' => 'news']), data: ['when' => 'always']);

    expect($form->refresh()->integrations[0]['conditions'])->toBe([]);
});

it('saves a switch, a copy and a delete straight away', function () {
    $form = integratedForm([['id' => 'news', 'class' => NewsletterIntegration::class, 'list' => 'Klanten']]);
    $page = editIntegrations($form);

    $page->callAction(onIntegrations('toggle', ['item' => 'news']));
    expect($form->refresh()->integrations[0]['enabled'])->toBeFalse();

    $page->callAction(onIntegrations('clone', ['item' => 'news']));
    expect($form->refresh()->integrations)->toHaveCount(2)
        ->and($form->integrations[1]['list'])->toBe('Klanten')
        ->and($form->integrations[1]['id'])->not->toBe('news');

    $page->callAction(onIntegrations('delete', ['item' => 'news']));
    expect($form->refresh()->integrations)->toHaveCount(1);
});

it('forgets a setting the slide-over no longer shows', function () {
    $form = integratedForm();
    $page = editIntegrations($form);

    $page->callAction(onIntegrations('add', ['class' => WebhookIntegration::class]), data: ['url' => 'https://hooks.example.test', 'auth' => 'bearer', 'token' => 'abc'])
        ->assertHasNoActionErrors();
    $id = $form->refresh()->integrations[0]['id'];

    expect(Crypt::decryptString($form->integrations[0]['token']))->toBe('abc');

    $page->mountAction(onIntegrations('edit', ['item' => $id]))
        ->assertSchemaStateSet(['token' => 'abc'])
        ->fillForm(['auth' => 'none'])
        ->callMountedAction();

    expect($form->refresh()->integrations[0])->auth->toBe('none')->not->toHaveKey('token');
});

it('shows on a card what an integration maps, a field that is gone, and how it ran', function () {
    $form = integratedForm([['id' => 'news', 'class' => NewsletterIntegration::class, 'list' => 'Klanten', 'mapping' => [
        'email' => 'email',
        'first_name' => 'weg',
        'note' => '<p>Via <span data-type="mergeTag" data-id="form_title"></span></p>',
    ]]]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => []]);

    foreach (['succeeded', 'succeeded', 'failed'] as $status) {
        $submission->integrationLogs()->create(['integration_id' => 'news', 'integration' => NewsletterIntegration::class, 'status' => $status, 'ran_at' => now()]);
    }

    editIntegrations($form)
        ->assertSee(['E-mailadres', 'E-mail', 'weg (no longer exists)', 'Voornaam', 'Via', 'Extra info'])
        ->assertSeeHtml('ffb-recipient-broken')
        ->assertSee(['2 times succeeded', '1 failure this week']);
});

it('takes an integration along when a key is renamed, and names it where a field is used', function () {
    $form = integratedForm([['id' => 'news', 'class' => NewsletterIntegration::class, 'list' => 'Klanten',
        'mapping' => ['email' => 'email', 'first_name' => 'voornaam', 'note' => '<p><span data-type="mergeTag" data-id="voornaam"></span></p>'],
        'conditions' => [['key' => 'voornaam', 'operator' => 'not_empty']],
    ]]);
    $page = editIntegrations($form);
    $voornaam = array_key_first($page->get('data.custom.fields'));
    $canvas = collect($page->instance()->getSchema('form')->getFlatComponents(withHidden: true))->first(fn ($component): bool => $component instanceof FormCanvas);

    expect($canvas->getKeyUsages($voornaam))->toBe(['the integration Newsletter']);

    $page->callAction(TestAction::make('edit')->schemaComponent('custom.fields', schema: 'form')->arguments(['item' => $voornaam]), data: ['key' => 'roepnaam'])
        ->call('save');

    $integration = $form->refresh()->integrations[0];

    expect($integration['mapping']['first_name'])->toBe('roepnaam')
        ->and($integration['mapping']['note'])->toContain('data-id="roepnaam"')
        ->and($integration['conditions'][0]['key'])->toBe('roepnaam');
});

it('sends a test with the latest submission, without logging it', function () {
    Http::fake();
    $form = integratedForm([['id' => 'hook', 'class' => WebhookIntegration::class, 'url' => 'https://hooks.example.test/forms', 'auth' => 'none']]);
    FormSubmission::create(['form_id' => $form->id, 'data' => ['voornaam' => 'Jan']]);

    editIntegrations($form)
        ->callAction(onIntegrations('edit', ['item' => 'hook', 'test' => true]))
        ->assertNotified('Test succeeded');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://hooks.example.test/forms' && $request['data'] === ['voornaam' => 'Jan']);
    expect(FormSubmissionIntegrationLog::count())->toBe(0);
});
