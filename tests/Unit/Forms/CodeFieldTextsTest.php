<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\ConsentField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\EmailField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\RadioField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SubmitField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class QuoteRequestForm extends FormType
{
    public function fields(): array
    {
        return [
            TextInputField::make('naam')->label('Naam')->placeholder('Je naam')->required(),
            RadioField::make('soort')->label('Soort')->options(['particulier' => 'Particulier', 'zakelijk' => 'Zakelijk'])->required(),
            EmailField::make('email')->label('E-mail')->description('Voor de bevestiging')->editable(['label']),
            (new ConsentField(['key' => 'voorwaarden', 'text' => '<p>Ik ga akkoord met de voorwaarden.</p>']))->required()->editable(false),
            SubmitField::make('verstuur')->label('Versturen'),
        ];
    }
}

beforeEach(function () {
    view()->share('errors', new ViewErrorBag());
    config(['filament-form-builder.types.quote' => QuoteRequestForm::class]);
});

function quoteForm(array $overrides = []): Form
{
    return Form::create([
        'title' => 'Offerte ' . Str::random(6),
        'template' => 'quote',
        'custom' => ['overrides' => $overrides],
        'submit_notifications' => [['type' => 'content', 'content' => '<p>Bedankt!</p>']],
    ]);
}

/**
 * @return array<string, mixed>
 */
function quoteField(Form $form, string $key): array
{
    foreach ($form->getFields() as $field) {
        if ($field->getKey() === $key) {
            return (array) $field;
        }
    }

    return [];
}

it('shows the texts an editor changed on fields from code, everywhere the fields are read', function () {
    $form = quoteForm([
        'naam' => ['label' => 'Uw naam', 'placeholder' => 'Voor- en achternaam'],
        'soort' => ['options' => ['zakelijk' => 'Voor mijn bedrijf']],
        'verstuur' => ['label' => 'Offerte aanvragen'],
    ]);

    expect($form->getSubmissionFields())->toMatchArray(['naam' => 'Uw naam', 'soort' => 'Soort'])
        ->and(Blade::render('<x-render-form :form="$form" />', ['form' => $form]))
        ->toContain('Uw naam')
        ->toContain('placeholder="Voor- en achternaam"')
        ->toContain('Voor mijn bedrijf')
        ->toContain('Particulier')
        ->toContain('Offerte aanvragen');

    test()->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['soort' => 'zakelijk'])
        ->assertSessionHasErrors(['naam' => 'The Uw naam field is required.']);

    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Jan', 'soort' => 'zakelijk']]);

    expect($submission->getValues()['soort'])->toBe('Voor mijn bedrijf')
        ->and($submission->data['soort'])->toBe('zakelijk');
});

it('changes only what a field from code allows, and keeps the code for an empty text', function () {
    $form = quoteForm([
        'naam' => ['label' => '  ', 'required' => false, 'key' => 'iets_anders'],
        'soort' => ['options' => ['bestaat_niet' => 'Nieuw', 'zakelijk' => '']],
        'email' => ['label' => 'Uw e-mail', 'description' => 'Wordt genegeerd'],
        'voorwaarden' => ['text' => '<p>Iets anders</p>'],
    ]);

    expect(quoteField($form, 'naam'))->label->toBe('Naam')->required->toBeTrue()->key->toBe('naam')
        ->and(array_column(quoteField($form, 'soort')['options'], 'label', 'value'))->toBe(['particulier' => 'Particulier', 'zakelijk' => 'Zakelijk'])
        ->and(quoteField($form, 'email'))->label->toBe('Uw e-mail')->description->toBe('Voor de bevestiging')
        ->and(quoteField($form, 'voorwaarden')['text'])->toBe('<p>Ik ga akkoord met de voorwaarden.</p>');
});

it('keeps only what differs from the code when an editor changes a field on the canvas', function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
    $form = quoteForm();
    $editTexts = fn (string $key, bool $reset = false): TestAction => TestAction::make('editCodeField')->schemaComponent('custom.fields', schema: 'form')->arguments(['key' => $key, ...($reset ? ['reset' => true] : [])]);
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);

    $page->mountAction($editTexts('soort'))
        ->assertSchemaStateSet(['label' => 'Soort'])
        ->callMountedAction()
        ->assertHasNoActionErrors();
    $page->callAction($editTexts('naam'), data: ['label' => 'Uw naam', 'placeholder' => 'Je naam'])
        ->assertHasNoActionErrors()
        ->assertSee('Uw naam')
        ->assertSeeHtml('ffb-canvas-badge-primary')
        ->call('save');

    expect($form->refresh()->custom['overrides'])->toBe(['naam' => ['label' => 'Uw naam']]);

    $page->callAction($editTexts('naam', reset: true))->call('save');

    expect($form->refresh()->custom['overrides'] ?? [])->toBe([]);
});

it('lets an editor change the labels of the options, never their values or how many there are', function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
    $form = quoteForm();
    $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);

    $page->mountAction(TestAction::make('editCodeField')->schemaComponent('custom.fields', schema: 'form')->arguments(['key' => 'soort']));
    $options = $page->get('mountedActions.0.data.options');
    $first = array_key_first($options);
    $last = array_key_last($options);

    $page->set("mountedActions.0.data.options.{$first}.value", 'gehackt')
        ->set("mountedActions.0.data.options.{$last}.label", 'Voor mijn bedrijf')
        ->callMountedAction()
        ->call('save');

    expect($form->refresh()->custom['overrides'])->toBe(['soort' => ['options' => ['zakelijk' => 'Voor mijn bedrijf']]]);
});

it('offers no texts of a field that may not change', function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));
    $form = quoteForm();

    $html = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])->html();
    $opens = fn (string $key): bool => str_contains($html, 'editCodeField') && str_contains($html, '\\u0022key\\u0022:\\u0022' . $key . '\\u0022');

    expect($opens('naam'))->toBeTrue()
        ->and($opens('verstuur'))->toBeTrue()
        ->and($opens('voorwaarden'))->toBeFalse();
});
