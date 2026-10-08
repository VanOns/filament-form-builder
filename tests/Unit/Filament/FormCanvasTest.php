<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use Tests\Fixtures\ApplicationForm;
use Tests\Fixtures\HalfRowForm;
use VanOns\FilamentFormBuilder\Classes\MergeTags;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\FormCanvas;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;
use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Models\Form;

beforeEach(function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
});

class FullRowField extends TextAreaField
{
    public static function minWidth(): FieldWidth
    {
        return FieldWidth::FULL;
    }
}

function canvasForm(array $fields = [], array $attributes = []): Form
{
    return Form::create([
        'title' => 'Contact',
        'template' => 'custom',
        'custom' => ['fields' => $fields],
        'submit_notifications' => [['type' => 'content', 'content' => '<p>Bedankt!</p>']],
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
        fn ($field) => $field->label->toBe('Voornaam')->key->toBe('voornaam')->type->toBe('text')->column_span->toBe(12),
        fn ($field) => $field->label->toBe('Achternaam'),
    );
});

it('gives a copy a key of its own', function () {
    $form = canvasForm([['type' => 'text', 'label' => 'Naam', 'key' => 'naam']]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_key_first($page->get('data.custom.fields'));

    $page->mountAction(onCanvas('clone', ['item' => $uuid]))
        ->assertActionMounted(onCanvas('clone', ['item' => $uuid]))
        ->callMountedAction()
        ->call('save');

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

it('fills a field from a parameter of the page URL, set on the canvas', function () {
    $form = canvasForm([['type' => 'text', 'label' => 'Vacature', 'key' => 'vacature']]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_keys($page->get('data.custom.fields'))[0];

    $page->callAction(onCanvas('edit', ['item' => $uuid]), data: ['queryParameter' => 'vacature?'])
        ->assertHasActionErrors(['queryParameter']);

    $page->callAction(onCanvas('edit', ['item' => $uuid]), data: ['queryParameter' => 'vacature'])
        ->call('save');

    expect(savedFields($form)[0]['queryParameter'])->toBe('vacature');

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSeeHtml('<span class="ffb-canvas-badge-text">?vacature</span>');
});

it('gives a field a short column name and shows it as a column, set on the canvas', function () {
    $form = canvasForm([['type' => 'number', 'label' => 'Hoeveel uur per week wil je werken?', 'key' => 'uren']]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_keys($page->get('data.custom.fields'))[0];

    $page->mountAction(onCanvas('edit', ['item' => $uuid]))
        ->assertFormFieldExists('columnLabel');

    $page->callAction(onCanvas('edit', ['item' => $uuid]), data: ['columnLabel' => 'Uren', 'showColumn' => true])
        ->call('save');

    expect(savedFields($form)[0])->toMatchArray(['columnLabel' => 'Uren', 'showColumn' => true]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSeeHtml('title="Shown as a column in the submissions by default"');
});

it('asks a consent for no second short name', function () {
    $form = canvasForm([['type' => 'consent', 'text' => '<p>Akkoord</p>', 'label' => 'Privacy', 'key' => 'privacy']]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_keys($page->get('data.custom.fields'))[0];

    $page->mountAction(onCanvas('edit', ['item' => $uuid]))
        ->assertFormFieldExists('showColumn')
        ->assertFormFieldDoesNotExist('columnLabel');
});

it('takes conditions, notifications and outcomes along when a key is renamed', function () {
    $form = canvasForm([
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
        ['type' => 'textarea', 'label' => 'Bericht', 'key' => 'bericht', 'conditions' => [['key' => 'voornaam', 'operator' => 'not_empty', 'value' => null]]],
    ], [
        'notifications' => [['subject' => 'Van {{ $voornaam }}', 'content' => '<p>{{ $voornaam }}</p>', 'to' => ['field:voornaam']]],
        'submit_notifications' => [
            ['type' => 'url', 'url' => 'https://example.test', 'query' => 'naam={{ $voornaam }}', 'conditions' => [['key' => 'voornaam', 'operator' => 'not_empty']]],
            ['type' => 'content', 'content' => '<p>Bedankt <span data-type="mergeTag" data-id="voornaam"></span></p>'],
        ],
    ]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_key_first($page->get('data.custom.fields'));

    $page->callAction(onCanvas('edit', ['item' => $uuid]), data: ['key' => 'roepnaam'])->call('save');

    $form->refresh();

    expect(array_values($form->custom['fields'][1]['conditions'])[0]['key'])->toBe('roepnaam')
        // Opened in the editor, the text from before became merge tags.
        ->and(MergeTags::ids($form->notifications[0]['subject']))->toBe(['roepnaam'])
        ->and(MergeTags::ids($form->notifications[0]['content']))->toBe(['roepnaam'])
        // Saved in the current shape, the receivers of before became `to`.
        ->and($form->notifications[0]['to'])->toBe(['field:roepnaam'])
        ->and($form->getSubmitNotifications()[0]['conditions'][0]['key'])->toBe('roepnaam')
        ->and($form->getSubmitNotifications()[0]['query'])->toBe('naam={{ $roepnaam }}')
        ->and(MergeTags::ids($form->getSubmitNotifications()[1]['content']))->toBe(['roepnaam']);
});

it('says where a field is still used before it is deleted', function () {
    $form = canvasForm([
        ['type' => 'text', 'label' => 'Voornaam', 'key' => 'voornaam'],
        ['type' => 'textarea', 'label' => 'Bericht', 'key' => 'bericht', 'conditions' => [['key' => 'voornaam', 'operator' => 'not_empty']]],
    ], [
        'notifications' => [['subject' => '<p>Van <span data-type="mergeTag" data-id="voornaam"></span></p>', 'content' => '<p>Hoi</p>', 'to' => ['hr@example.test']]],
        'submit_notifications' => [['type' => 'content', 'content' => '<p>Bedankt <span data-type="mergeTag" data-id="voornaam"></span></p>']],
    ]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    [$voornaam, $bericht] = array_keys($page->get('data.custom.fields'));
    $canvas = collect($page->instance()->getSchema('form')->getFlatComponents(withHidden: true))->first(fn ($component): bool => $component instanceof FormCanvas);

    expect($canvas->getKeyUsages($voornaam))->toBe(['the conditions of Bericht', 'the e-mail “Van Voornaam”', 'After submitting'])
        ->and($canvas->getKeyUsages($bericht))->toBe([]);
});

it('sets a field to the width picked for it', function () {
    $form = canvasForm([['type' => 'text', 'label' => 'Naam', 'key' => 'naam']]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_key_first($page->get('data.custom.fields'));

    $page->callAction(onCanvas('resize', ['item' => $uuid, 'width' => 4]))->call('save');
    expect(savedFields($form)[0]['column_span'])->toBe(4);

    $page->callAction(onCanvas('resize', ['item' => $uuid, 'width' => 5]))->call('save');
    expect(savedFields($form)[0]['column_span'])->toBe(4);
});

/**
 * @param  array<int, int>  $spans
 * @param  array<int, string>  $types  field type by position, text otherwise
 */
function rowOf(array $spans, array $types = []): Form
{
    return canvasForm(array_map(
        fn (int $span, int $index): array => ['type' => $types[$index] ?? 'text', 'label' => "Veld {$index}", 'key' => "veld_{$index}", 'column_span' => $span],
        $spans,
        array_keys($spans),
    ), ['title' => 'Rij ' . implode('-', $spans) . ' ' . uniqid()]);
}

it('gives a new field the room left on the last row', function () {
    $form = rowOf([6]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction(onCanvas('add', ['type' => 'text']), data: ['label' => 'Achternaam'])
        ->callAction(onCanvas('add', ['type' => 'text']), data: ['label' => 'Telefoon'])
        ->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([6, 6, 12]);
});

it('fits a field dropped between two thirds into the third that is left', function () {
    $form = rowOf([4, 4]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction(onCanvas('add', ['type' => 'text', 'position' => 1]), data: ['label' => 'Tussenvoegsel'])
        ->call('save');

    expect(array_column(savedFields($form), 'key'))->toBe(['veld_0', 'tussenvoegsel', 'veld_1'])
        ->and(array_column(savedFields($form), 'column_span'))->toBe([4, 4, 4]);
});

it('starts a field at the row it was dropped in front of when the row before is full', function () {
    $form = rowOf([12, 9]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction(onCanvas('add', ['type' => 'text', 'position' => 1]), data: ['label' => 'Huisnummer'])
        ->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([12, 3, 9]);
});

/**
 * @param  array<int, int>  $spans
 * @param  array<int, string>  $types
 * @return array<int, int>
 */
function resized(array $spans, int $field, int $width, array $types = []): array
{
    $form = rowOf($spans, $types);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_keys($page->get('data.custom.fields'))[$field];

    $page->callAction(onCanvas('resize', ['item' => $uuid, 'width' => $width]))->call('save');

    return array_column(savedFields($form), 'column_span');
}

it('keeps a full row full when one of its fields changes width', function () {
    expect(resized([4, 4, 4], 0, 6))->toBe([6, 3, 3])
        ->and(resized([6, 6], 0, 3))->toBe([3, 9]);
});

it('narrows the rest of a row a widened field would overflow', function () {
    expect(resized([6, 4], 0, 9))->toBe([9, 3]);
});

it('leaves the rest of a row that was not full alone while it still fits', function () {
    expect(resized([4, 4], 0, 6))->toBe([6, 4]);
});

it('moves what no longer fits to a full row of its own below', function () {
    expect(resized([4, 4, 4], 0, 8))->toBe([8, 4, 12])
        ->and(resized([6, 6], 0, 12))->toBe([12, 12])
        ->and(resized([3, 3, 3, 3], 0, 6))->toBe([6, 3, 3, 12]);
});

it('keeps the fields before a widened one on its row and moves the later ones on', function () {
    $form = rowOf([4, 4, 4, 12]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_keys($page->get('data.custom.fields'))[2];

    $page->callAction(onCanvas('resize', ['item' => $uuid, 'width' => 8]))->call('save');

    expect(array_column(savedFields($form), 'key'))->toBe(['veld_0', 'veld_2', 'veld_1', 'veld_3'])
        ->and(array_column(savedFields($form), 'column_span'))->toBe([4, 8, 12, 12]);
});

it('takes the width a dropped field showed while it was dragged', function () {
    $form = rowOf([6]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction(onCanvas('add', ['type' => 'text', 'position' => 1, 'width' => 4]), data: ['label' => 'Achternaam'])
        ->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([6, 4]);
});

it('keeps a field at the narrowest width its type works at', function () {
    expect(resized([12], 0, 3, ['textarea']))->toBe([4])
        ->and(resized([4, 4, 4], 0, 6, [1 => 'textarea']))->toBe([6, 6, 12])
        ->and(resized([6, 6], 0, 8, [1 => 'textarea']))->toBe([8, 4]);
});

it('only offers the widths a field can take', function () {
    $form = canvasForm([['type' => 'textarea', 'label' => 'Bericht', 'key' => 'bericht']]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSee('A third')
        ->assertDontSee('A quarter');
});

it('shows only the width of a field that can take no other', function () {
    config(['filament-form-builder.fields.full_row' => FullRowField::class]);

    $form = canvasForm([['type' => 'full_row', 'label' => 'Toelichting', 'key' => 'toelichting']]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSeeHtml('<span class="ffb-canvas-item-span">1/1</span>')
        ->assertDontSeeHtml('ffb-canvas-width-trigger');
});

it('puts the submit button on a row of its own', function () {
    $form = rowOf([6]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction(onCanvas('add', ['type' => 'submit', 'width' => 6]), data: ['label' => 'Versturen'])
        ->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([6, 12]);
});

it('gives a new field a row of its own where the room left is too narrow for it', function () {
    $form = rowOf([9]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction(onCanvas('add', ['type' => 'textarea']), data: ['label' => 'Bericht'])
        ->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([9, 12]);
});

it('shares a full row with a field dropped against one of its fields, as the canvas showed', function () {
    $form = rowOf([6, 6]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    [$first, $second] = array_keys($page->get('data.custom.fields'));

    $page->callAction(
        onCanvas('add', ['type' => 'text', 'position' => 1, 'width' => 4, 'spans' => [$first => 4, $second => 4]]),
        data: ['label' => 'Tussenvoegsel'],
    )->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([4, 4, 4]);
});

it('ignores a shared width that is none and keeps one at the field minimum', function () {
    $form = rowOf([12, 12], [1 => 'textarea']);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    [$first, $second] = array_keys($page->get('data.custom.fields'));

    $page->callAction(
        onCanvas('add', ['type' => 'text', 'width' => 12, 'spans' => [$first => 5, $second => 3]]),
        data: ['label' => 'Telefoon'],
    )->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([12, 4, 12]);
});

it('closes up the row a field is deleted from', function () {
    $form = rowOf([4, 4, 4]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_keys($page->get('data.custom.fields'))[1];

    $page->mountAction(onCanvas('delete', ['item' => $uuid]))->callMountedAction()->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([6, 6]);
});

it('spreads the room a field leaves as evenly as the row allows', function () {
    $form = rowOf([6, 3, 3]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_key_first($page->get('data.custom.fields'));

    $page->mountAction(onCanvas('delete', ['item' => $uuid]))->callMountedAction()->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([6, 6]);
});

it('puts a copy next to its original, sharing the row when it is full', function () {
    $copied = function (array $spans): array {
        $form = rowOf($spans);
        $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);

        $page->callAction(onCanvas('clone', ['item' => array_key_first($page->get('data.custom.fields'))]))->call('save');

        return array_column(savedFields($form), 'column_span');
    };

    expect($copied([6, 6]))->toBe([4, 4, 4])
        ->and($copied([12]))->toBe([6, 6])
        ->and($copied([4, 4]))->toBe([4, 4, 4])
        ->and($copied([3, 3, 3, 3]))->toBe([3, 3, 3, 3, 12]);
});

it('moves a field to another row with the widths the canvas showed', function () {
    $form = rowOf([4, 4, 4, 12]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    [$first, $second, $third, $fourth] = array_keys($page->get('data.custom.fields'));

    $page->callAction(onCanvas('reorder', [
        'items' => [$first, $third, $second, $fourth],
        'spans' => [$first => 6, $third => 6, $second => 6, $fourth => 6],
    ]))->call('save');

    expect(array_column(savedFields($form), 'key'))->toBe(['veld_0', 'veld_2', 'veld_1', 'veld_3'])
        ->and(array_column(savedFields($form), 'column_span'))->toBe([6, 6, 6, 6]);
});

it('keeps to halves when the project lays fields out in two columns', function () {
    config(['filament-form-builder.layout' => 'two_columns']);

    expect(resized([6, 6], 0, 4))->toBe([6, 6])
        ->and(resized([12], 0, 6))->toBe([6]);

    $form = rowOf([6, 6]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);

    $page->callAction(onCanvas('clone', ['item' => array_key_first($page->get('data.custom.fields'))]))
        ->callAction(onCanvas('add', ['type' => 'text']), data: ['label' => 'Telefoon'])
        ->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([6, 6, 12, 12])
        ->and(rowOf([4, 8])->getFields()[0]->getColumnSpan())->toBe(6);
});

it('offers no widths at all when every field takes a full row', function () {
    config(['filament-form-builder.layout' => 'full_width']);

    $form = rowOf([6, 6]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertDontSeeHtml('ffb-canvas-width-trigger')
        ->callAction(onCanvas('add', ['type' => 'text', 'width' => 6]), data: ['label' => 'Telefoon'])
        ->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([6, 6, 12])
        ->and(array_map(fn ($field): int => $field->getColumnSpan(), $form->fresh()->getFields()))->toBe([12, 12, 12]);
});

it('keeps the fields an editor builds on rows of their own, apart from those in code', function () {
    config(['filament-form-builder.types.half_row' => HalfRowForm::class]);

    $form = canvasForm([['type' => 'text', 'label' => 'Plaats', 'key' => 'plaats', 'column_span' => 6]], ['template' => 'half_row']);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSee('Custom fields')
        ->callAction(onCanvas('add', ['type' => 'text']), data: ['label' => 'Postcode'])
        ->call('save');

    expect(array_column(savedFields($form), 'column_span'))->toBe([6, 6]);
});

it('says in words when a field shows', function () {
    $form = canvasForm([
        ['type' => 'radio', 'label' => 'Rijbewijs', 'key' => 'rijbewijs', 'options' => [['value' => 'ja', 'label' => 'Ja']]],
        ['type' => 'text', 'label' => 'Vervoer', 'key' => 'vervoer', 'conditions' => [['key' => 'rijbewijs', 'operator' => 'equals', 'value' => 'ja']]],
        ['type' => 'text', 'label' => 'Toelichting', 'key' => 'toelichting', 'conditionMatch' => 'any', 'conditions' => [
            ['key' => 'rijbewijs', 'operator' => 'not_empty'],
            ['key' => 'vervoer', 'operator' => 'empty'],
        ]],
    ]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSee('If Rijbewijs = Ja')
        ->assertSee('2 conditions, one must hold');
});

it('groups the palette and shows a hidden field by its default value', function () {
    $form = canvasForm([['type' => 'text', 'label' => 'Bron', 'key' => 'bron', 'hidden' => true, 'defaultValue' => 'website']]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSee(['Input', 'Choice', 'Layout and sending'])
        ->assertSee('Default value: website');
});

it('offers reCAPTCHA in the palette only once it is set up', function () {
    $offered = fn (): bool => collect(FormCanvas::make('custom')->getPaletteGroups())->contains(fn (array $group): bool => isset($group['recaptcha']));

    config(['filament-form-builder.recaptcha' => ['enabled' => false, 'key' => 'key', 'secret' => 'secret']]);
    expect($offered())->toBeFalse();

    config(['filament-form-builder.recaptcha' => ['enabled' => true, 'key' => '', 'secret' => 'secret']]);
    expect($offered())->toBeFalse();

    config(['filament-form-builder.recaptcha' => ['enabled' => true, 'key' => 'key', 'secret' => 'secret']]);
    expect($offered())->toBeTrue();
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

it('keeps the order the options of a choice field were dragged into', function () {
    $form = canvasForm([['type' => 'radio', 'label' => 'Kleur', 'key' => 'kleur', 'options' => [
        ['value' => 'rood', 'label' => 'Rood'],
        ['value' => 'blauw', 'label' => 'Blauw'],
        ['value' => 'groen', 'label' => 'Groen'],
    ]]]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);

    $page->mountAction(onCanvas('edit', ['item' => array_keys($page->get('data.custom.fields'))[0]]));
    [$rood, $blauw, $groen] = array_keys($page->get('mountedActions.0.data.options'));

    $page->callAction(TestAction::make('reorder')->schemaComponent('options', schema: 'mountedActionSchema0')->arguments(['items' => [$groen, $rood, $blauw]]))
        ->callMountedAction()
        ->call('save');

    expect(array_column(savedFields($form)[0]['options'], 'value'))->toBe(['groen', 'rood', 'blauw']);
});

it('asks a condition on a number for a number', function () {
    $form = canvasForm([
        ['type' => 'number', 'label' => 'Leeftijd', 'key' => 'leeftijd'],
        ['type' => 'text', 'label' => 'Rijbewijs', 'key' => 'rijbewijs'],
    ]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
    $uuid = array_keys($page->get('data.custom.fields'))[1];
    $condition = fn (string $value): array => ['conditions' => [['key' => 'leeftijd', 'operator' => 'at_least', 'value' => $value]]];

    $page->callAction(onCanvas('edit', ['item' => $uuid]), data: $condition('volwassen'))
        ->assertHasActionErrors()
        ->callAction(onCanvas('edit', ['item' => $uuid]), data: $condition('18'))
        ->assertHasNoActionErrors();
});

it('shows the fields of the form type around the canvas and keeps their keys free', function () {
    config(['filament-form-builder.types.application' => ApplicationForm::class]);

    $form = canvasForm(attributes: ['template' => 'application']);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSee('Defined in code')
        ->callAction(onCanvas('add', ['type' => 'text']), data: ['label' => 'Naam'])
        ->call('save');

    expect(array_column(savedFields($form), 'key'))->toBe(['naam_2']);
});

it('keeps the keys of the values a type adds itself free as well', function () {
    config(['filament-form-builder.types.application' => ApplicationForm::class]);

    $form = canvasForm(attributes: ['template' => 'application']);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction(onCanvas('add', ['type' => 'text']), data: ['label' => 'Ontvangen via'])
        ->call('save');

    expect(array_column(savedFields($form), 'key'))->toBe(['ontvangen_via_2']);
});

it('flags a field whose key the form type took over in code', function () {
    config(['filament-form-builder.types.application' => ApplicationForm::class]);

    $form = canvasForm([['type' => 'text', 'label' => 'Naam', 'key' => 'naam'], ['type' => 'text', 'label' => 'Motivatie', 'key' => 'motivatie']], ['template' => 'application']);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSeeHtml('ffb-canvas-item-taken')
        ->assertSee('Key taken in code')
        ->assertSee('The form type has a field with the key naam itself');
});

it('shows the fields of a type without room for more, without letting anyone add one', function () {
    $form = canvasForm([['type' => 'text', 'label' => 'Uit een eerder type', 'key' => 'eerder']], ['template' => 'contact']);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSee('The fields of this form are defined in code.')
        ->assertSee('Company name')
        // Not assertDontSee: Livewire 4.3 leaves the relation manager's snapshot, which holds the form, in the HTML.
        ->assertDontSeeHtml('data-item=')
        ->assertDontSeeHtml('data-type="text"')
        ->call('save')
        ->assertHasNoErrors();

    expect(array_column(savedFields($form), 'key'))->toBe(['eerder']);
});

it('keeps the fields an editor built while the form has a type without fields', function () {
    config(['filament-form-builder.types.bare' => get_class(new class (new Form()) extends FormType {})]);

    $form = canvasForm([['type' => 'text', 'label' => 'Naam', 'key' => 'naam']], ['template' => 'bare']);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertDontSee('The fields of this form are defined in code.')
        ->call('save')
        ->assertHasNoErrors();

    expect(array_column(savedFields($form), 'key'))->toBe(['naam']);
});

it('leaves the field types a panel does not want out of the palette', function () {
    $offered = fn (): array => array_merge(...array_values(array_map('array_keys', FormCanvas::make('custom')->getPaletteGroups())));

    expect($offered())->toContain('phone', 'date', 'consent');

    config(['filament-form-builder.without_fields' => ['phone']]);
    expect($offered())->not->toContain('phone');

    FilamentFormBuilderPlugin::get()->withoutFields(['date', 'consent']);
    expect($offered())->toContain('phone')->not->toContain('date')->not->toContain('consent');
});
