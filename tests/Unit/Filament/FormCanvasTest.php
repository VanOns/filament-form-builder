<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm;

beforeEach(function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
});

function canvasForm(array $fields = [], array $attributes = []): Form
{
    return Form::create([
        'title' => 'Contact',
        'template' => CustomForm::class,
        'custom' => ['fields' => $fields],
        'submit_notification_type' => 'content',
        'submit_notification_content' => 'Bedankt!',
        ...$attributes,
    ]);
}

function onCanvas(string $action, array $arguments = []): TestAction
{
    return TestAction::make($action)->schemaComponent('custom.fields', schema: 'form')->arguments($arguments);
}

/**
 * @return list<array<string, mixed>>
 */
function savedFields(Form $form): array
{
    return array_values($form->fresh()->custom['fields']);
}

it('adds a field at the spot it was dropped, full width and with its own key', function () {
    $form = canvasForm([['type' => 'text', 'label' => 'Achternaam', 'key' => 'achternaam']]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction(onCanvas('add', ['type' => 'text', 'position' => 0]), data: ['label' => 'Voornaam'])
        ->call('save');

    expect(savedFields($form))->sequence(
        fn ($field) => $field->label->toBe('Voornaam')->key->toBe('voornaam')->type->toBe('text')->column_span->toBe(2),
        fn ($field) => $field->label->toBe('Achternaam'),
    );
});

it('gives a copy a key of its own', function () {
    $form = canvasForm([['type' => 'text', 'label' => 'Naam', 'key' => 'naam']]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_key_first($page->get('data.custom.fields'));

    $page->callAction(onCanvas('clone', ['item' => $uuid]))->call('save');

    expect(array_column(savedFields($form), 'key'))->toBe(['naam', 'naam_2']);
});

it('refuses a key another field already has', function () {
    $form = canvasForm([
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
        ['type' => 'text', 'label' => 'Achternaam', 'key' => 'achternaam'],
    ]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_keys($page->get('data.custom.fields'))[1];

    $page->callAction(onCanvas('edit', ['item' => $uuid]), data: ['key' => 'voornaam'])
        ->assertHasActionErrors(['key']);
});

it('takes conditions and notifications along when a key is renamed', function () {
    $form = canvasForm([
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
        ['type' => 'textarea', 'label' => 'Bericht', 'key' => 'bericht', 'conditions' => [['key' => 'voornaam', 'operator' => 'not_empty', 'value' => null]]],
    ], [
        'notifications' => [['subject' => 'Van {{ $voornaam }}', 'content' => '<p>{{ $voornaam }}</p>', 'receivers' => ['voornaam']]],
    ]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_key_first($page->get('data.custom.fields'));

    $page->callAction(onCanvas('edit', ['item' => $uuid]), data: ['key' => 'roepnaam'])->call('save');

    $form->refresh();

    expect(array_values($form->custom['fields'][1]['conditions'])[0]['key'])->toBe('roepnaam')
        ->and($form->notifications[0]['subject'])->toBe('Van {{ $roepnaam }}')
        ->and($form->notifications[0]['content'])->toContain('{{ $roepnaam }}')
        ->and($form->notifications[0]['receivers'])->toBe(['roepnaam']);
});

it('makes a field narrower and wider within the form columns', function () {
    $form = canvasForm([['type' => 'text', 'label' => 'Naam', 'key' => 'naam', 'column_span' => 2]]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_key_first($page->get('data.custom.fields'));

    $page->callAction(onCanvas('narrow', ['item' => $uuid]))->call('save');
    expect(savedFields($form)[0]['column_span'])->toBe(1);
    $page->assertActionDisabled(onCanvas('narrow', ['item' => $uuid]));

    $page->callAction(onCanvas('widen', ['item' => $uuid]))->call('save');
    expect(savedFields($form)[0]['column_span'])->toBe(2);
    $page->assertActionDisabled(onCanvas('widen', ['item' => $uuid]));
});

it('reorders the fields the way they were dragged', function () {
    $form = canvasForm([
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
        ['type' => 'text', 'label' => 'Achternaam', 'key' => 'achternaam'],
    ]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuids = array_keys($page->get('data.custom.fields'));

    $page->callAction(onCanvas('reorder', ['items' => array_reverse($uuids)]))->call('save');

    expect(array_column(savedFields($form), 'key'))->toBe(['achternaam', 'voornaam']);
});

it('removes a field once the deletion is confirmed', function () {
    $form = canvasForm([
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
        ['type' => 'text', 'label' => 'Achternaam', 'key' => 'achternaam'],
    ]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_key_first($page->get('data.custom.fields'));

    $page->mountAction(onCanvas('delete', ['item' => $uuid]))
        ->assertActionMounted(onCanvas('delete', ['item' => $uuid]))
        ->callMountedAction()
        ->call('save');

    expect(array_column(savedFields($form), 'key'))->toBe(['achternaam']);
});

it('stores the conditions of a field with how they combine', function () {
    $form = canvasForm([
        ['type' => 'radio', 'label' => 'Onderwerp', 'key' => 'onderwerp', 'options' => [['value' => 'anders', 'label' => 'Anders']]],
        ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
        ['type' => 'textarea', 'label' => 'Bericht', 'key' => 'bericht'],
    ]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_keys($page->get('data.custom.fields'))[2];

    $page->callAction(onCanvas('edit', ['item' => $uuid]), data: [
        'conditionMatch' => 'any',
        'conditions' => [
            ['key' => 'onderwerp', 'operator' => 'equals', 'value' => 'anders'],
            ['key' => 'naam', 'operator' => 'not_empty'],
        ],
    ])->call('save');

    $bericht = $form->fresh()->getFields()[2];

    expect($bericht->hasConditions())->toBeTrue()
        ->and($bericht->getConditions()->toArray())->toBe([
            'match' => 'any',
            'rules' => [
                ['key' => 'onderwerp', 'operator' => 'equals', 'value' => 'anders'],
                ['key' => 'naam', 'operator' => 'not_empty', 'value' => null],
            ],
        ]);
});
