<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\QueryParameters;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\SubmitNotificationList;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Forms\CustomForm;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class NoRedirectForm extends CustomForm
{
    public function hasRedirect(): bool
    {
        return false;
    }
}

/**
 * @param  array<string, mixed>  $outcome  the outcome the form always has
 * @param  list<array<string, mixed>>  $rules  the outcomes for certain answers, before it
 */
function thankingForm(array $outcome = [], array $rules = []): Form
{
    return Form::create([
        'title' => 'Solliciteren ' . uniqid(),
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'email', 'label' => 'E-mailadres', 'key' => 'email'],
            ['type' => 'radio', 'label' => 'Vestiging', 'key' => 'vestiging', 'options' => [
                ['value' => 'utrecht', 'label' => 'Utrecht'],
                ['value' => 'zwolle', 'label' => 'Zwolle'],
            ]],
        ]],
        'submit_notifications' => [...$rules, ['type' => 'content', 'content' => '<p>Bedankt!</p>', ...$outcome]],
    ]);
}

function thankYouFor(Form $form): ?string
{
    return $form->getType()->resolveSubmitNotification(submissionTo($form))->getNotificationMessage();
}

function submissionTo(Form $form, array $data = []): FormSubmission
{
    return FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Jan', 'email' => 'jan@example.test', ...$data]]);
}

it('fills in the tags of the thank-you message, but never the link to the panel', function () {
    $message = thankYouFor(thankingForm(['content' => '<p>Bedankt <span data-type="mergeTag" data-id="naam"></span>!</p>'
        . '<p><span data-type="mergeTag" data-id="all_fields"></span></p><p><span data-type="mergeTag" data-id="submission_url"></span></p>']));

    expect($message)->toContain('Bedankt Jan!')
        ->toContain('<b>E-mailadres</b>: jan@example.test')
        ->not->toContain('/admin');
});

it('shows no message when nothing is left of it', function () {
    expect(thankYouFor(thankingForm(['content' => '<p><span data-type="mergeTag" data-id="weg"></span></p>'])))->toBeNull();
});

it('reads a query string as rows of text and tags and writes it back the same', function () {
    $tag = fn (string $id): string => '<span data-type="mergeTag" data-id="' . $id . '"></span>';
    $query = 'naam={{ $naam }}&bron=nieuws%20%26%20media&wie={{ $naam }}%20via%20{{ $form_title }}';

    expect(QueryParameters::parse($query))->toBe([
        ['name' => 'naam', 'value' => '<p>' . $tag('naam') . '</p>'],
        ['name' => 'bron', 'value' => '<p>nieuws &amp; media</p>'],
        ['name' => 'wie', 'value' => '<p>' . $tag('naam') . ' via ' . $tag('form_title') . '</p>'],
    ])
        ->and(QueryParameters::build(QueryParameters::parse($query)))->toBe($query)
        ->and(QueryParameters::build([['name' => 'leeg', 'value' => '']]))->toBe('leeg=')
        ->and(QueryParameters::build([]))->toBeNull()
        ->and(QueryParameters::parse('?naam={{$naam}}&'))->toBe([['name' => 'naam', 'value' => '<p>' . $tag('naam') . '</p>']])
        ->and(QueryParameters::parse('bedankt&naam={{ $naam }}'))->toBeNull()
        ->and(QueryParameters::parse('na me=jan'))->toBeNull();
});

it('edits the parameters as rows and keeps a query string with more than that as text', function () {
    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    $redirecting = ['type' => 'url', 'url' => 'https://example.test/bedankt'];
    $rows = thankingForm([...$redirecting, 'query' => 'naam={{ $naam }}']);
    $text = thankingForm([...$redirecting, 'query' => 'bedankt&naam={{ $naam }}']);

    Livewire::test(EditForm::class, ['record' => $rows->getRouteKey()])
        ->fillForm(['submit_notifications.default.query' => [
            ['name' => 'naam', 'value' => '<p><span data-type="mergeTag" data-id="naam"></span></p>'],
            ['name' => 'bron', 'value' => '<p>website</p>'],
        ]])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(EditForm::class, ['record' => $text->getRouteKey()])
        ->assertSchemaStateSet(['submit_notifications.default.query' => 'bedankt&naam={{ $naam }}'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($rows->refresh()->getSubmitNotifications()[0]['query'])->toBe('naam={{ $naam }}&bron=website')
        ->and($text->refresh()->getSubmitNotifications()[0]['query'])->toBe('bedankt&naam={{ $naam }}');
});

it('keeps the parameters behind a link until a form has some', function () {
    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    $redirecting = ['type' => 'url', 'url' => 'https://example.test/bedankt'];

    Livewire::test(EditForm::class, ['record' => thankingForm($redirecting)->getRouteKey()])
        ->assertFormFieldHidden('submit_notifications.default.query')
        ->callAction(TestAction::make('addQueryParameters')->schemaComponent('submit_notifications.default.query_link', schema: 'form'))
        ->assertFormFieldVisible('submit_notifications.default.query');

    Livewire::test(EditForm::class, ['record' => thankingForm([...$redirecting, 'query' => 'naam={{ $naam }}'])->getRouteKey()])
        ->assertFormFieldVisible('submit_notifications.default.query');
});

it('refuses a parameter name that would break the URL', function () {
    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    $form = thankingForm(['type' => 'url', 'url' => 'https://example.test/bedankt', 'query' => 'naam=jan']);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->fillForm(['submit_notifications.default.query' => [['name' => 'naam&admin=1', 'value' => '<p>jan</p>']]])
        ->call('save')
        ->assertHasFormErrors(['submit_notifications.default.query.0.name' => 'regex']);
});

it('shows where the latest submission would have sent its visitor', function () {
    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    $form = thankingForm(['type' => 'url', 'url' => 'https://example.test/bedankt', 'query' => 'naam={{ $naam }}']);
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Jan de Vries']]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSee("Where the visitor lands, with submission #{$submission->id}")
        ->assertSeeHtml('https://example.test/bedankt<span class="ffb-redirect-example-query">?naam=Jan%20de%20Vries</span>');
});

it('takes the first outcome whose conditions the answers meet, and the last one otherwise', function () {
    $utrecht = ['type' => 'url', 'url' => 'https://example.test/utrecht', 'conditions' => [['key' => 'vestiging', 'operator' => 'equals', 'value' => 'utrecht']]];
    $named = ['type' => 'content', 'content' => '<p>Hoi <span data-type="mergeTag" data-id="naam"></span></p>', 'conditions' => [['key' => 'naam', 'operator' => 'not_empty']]];
    $form = thankingForm([], [$utrecht, $named]);
    $resolve = fn (array $data) => $form->getType()->resolveSubmitNotification(submissionTo($form, $data));

    expect($resolve(['vestiging' => 'utrecht'])->getRedirectUrl())->toBe('https://example.test/utrecht')
        ->and($resolve(['vestiging' => 'zwolle'])->getNotificationMessage())->toContain('Hoi Jan')
        ->and($resolve(['vestiging' => 'zwolle', 'naam' => ''])->getNotificationMessage())->toBe('<p>Bedankt!</p>');
});

it('skips an outcome of a kind the form type does not allow', function () {
    config(['filament-form-builder.types.no_redirect' => NoRedirectForm::class]);

    $form = thankingForm([], [['type' => 'url', 'url' => 'https://example.test/utrecht']]);
    $form->update(['template' => 'no_redirect']);

    $type = $form->refresh()->getType()->resolveSubmitNotification(submissionTo($form));

    expect($type->getRedirectUrl())->toBeNull()
        ->and($type->getNotificationMessage())->toBe('<p>Bedankt!</p>');
});

it('saves the outcomes for certain answers before the one a form always has', function () {
    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    $form = thankingForm();
    $rule = ['type' => 'url', 'url' => 'https://example.test/utrecht', 'conditionMatch' => 'all', 'conditions' => [['key' => 'vestiging', 'operator' => 'equals', 'value' => 'utrecht']]];

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction(TestAction::make('add')->schemaComponent('submit_notifications.rules', schema: 'form'), data: $rule)
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('add')->schemaComponent('submit_notifications.rules', schema: 'form'), data: ['type' => 'content', 'content' => '<p>Zwolle</p>', 'conditions' => []])
        ->assertHasActionErrors(['conditions'])
        ->call('save')
        ->assertHasNoFormErrors();

    $outcomes = $form->refresh()->getSubmitNotifications();

    expect($outcomes)->toHaveCount(2)
        ->and($outcomes[0])->toMatchArray(['type' => 'url', 'url' => 'https://example.test/utrecht'])
        ->and($outcomes[0]['conditions'][0]['value'])->toBe('utrecht')
        ->and($outcomes[1])->toMatchArray(['type' => 'content', 'content' => '<p>Bedankt!</p>', 'conditions' => []]);
});

it('moves an outcome up and down', function () {
    $items = ['a' => ['id' => 'a'], 'b' => ['id' => 'b'], 'c' => ['id' => 'c']];

    expect(array_keys(SubmitNotificationList::move($items, 'c', -1)))->toBe(['a', 'c', 'b'])
        ->and(array_keys(SubmitNotificationList::move($items, 'a', -1)))->toBe(['a', 'b', 'c'])
        ->and(array_keys(SubmitNotificationList::insertAfter($items, 'a', ['id' => 'x'])))->toBe(['a', 'x', 'b', 'c']);
});
