<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\ApplicationForm;
use Tests\Fixtures\HalfRowForm;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class ThankingForm extends FormType
{
    public function fields(): array
    {
        return [TextInputField::make('naam')];
    }

    public function response(FormSubmission $submission): mixed
    {
        return response('Dank je, ' . $submission->data['naam']);
    }
}

class PostcodeForm extends FormType
{
    public function fields(): array
    {
        return [TextInputField::make('postcode')->required()];
    }

    public function beforeValidation(array $data): array
    {
        return [...$data, 'postcode' => strtoupper(str_replace(' ', '', (string) ($data['postcode'] ?? '')))];
    }
}

beforeEach(function () {
    config([
        'filament-form-builder.types.application' => ApplicationForm::class,
        'filament-form-builder.types.thanking' => ThankingForm::class,
        'filament-form-builder.types.postcode' => PostcodeForm::class,
    ]);
});

function applicationForm(array $customFields = []): Form
{
    return Form::create(['title' => 'Sollicitatie', 'template' => 'application', 'custom' => ['fields' => $customFields]]);
}

/**
 * @param  array<int, FormField>  $fields
 * @return array<int, string>
 */
function keysOf(array $fields): array
{
    return array_map(fn (FormField $field): string => $field->getKey(), $fields);
}

it('puts the fields an editor built where the type makes room for them', function () {
    $form = applicationForm([['type' => 'textarea', 'label' => 'Motivatie', 'key' => 'motivatie']]);

    expect(keysOf($form->getFields(inputsOnly: true)))->toBe(['naam', 'vacature', 'motivatie', 'privacy'])
        ->and($form->getType()->hasCustomFields())->toBeTrue();
});

it('leaves the fields an editor built out of a type without room for them', function () {
    $form = Form::create(['title' => 'Contact', 'template' => 'contact', 'custom' => ['fields' => [['type' => 'text', 'key' => 'extra']]]]);

    expect(keysOf($form->getFields(inputsOnly: true)))->not->toContain('extra')
        ->and($form->getType()->hasCustomFields())->toBeFalse();
});

it('validates the fields a type has in code', function () {
    $form = applicationForm();

    $this->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['privacy' => '1'])
        ->assertSessionHasErrors('naam');

    expect(FormSubmission::count())->toBe(0);
});

it('renders a hidden field as its default value', function () {
    Route::middleware('web')->get('sollicitatie/{form}', fn (Form $form) => Blade::render('<x-render-form :form="$form" />', ['form' => $form]));

    $this->get('sollicitatie/' . applicationForm()->id)
        ->assertSee('<input type="hidden" name="vacature" value="Adviseur" />', escape: false)
        ->assertDontSee('Vacature');
});

it('stores a hidden field and the values the type adds itself', function () {
    $form = applicationForm();

    $this->post(route('filament-form-builder.form.store', ['formId' => $form->id]), [
        'naam' => 'Jan',
        'vacature' => 'Senior adviseur',
        'privacy' => '1',
    ]);

    expect(FormSubmission::sole()->data)->toBe([
        'naam' => 'Jan',
        'vacature' => 'Senior adviseur',
        'privacy' => '1',
        'ontvangen_via' => 'website',
    ]);
});

it('never takes a value the type adds from the visitor', function () {
    expect(applicationForm()->getSubmittableKeys())->toBe(['naam', 'vacature', 'privacy']);
});

it('labels the values a type adds wherever answers are shown', function () {
    $form = applicationForm();
    $submission = FormSubmission::create(['form_id' => $form->id, 'data' => ['naam' => 'Jan', 'ontvangen_via' => 'website']]);

    expect($form->getSubmissionFields())->toHaveKey('ontvangen_via', 'Ontvangen via')
        ->and($form->getPlaceholderList())->toContain('{{ $ontvangen_via }}')
        ->and(array_map(fn ($answer) => [$answer->label, $answer->value], $submission->getAnswers()['current']))->toContain(['Ontvangen via', 'website']);
});

it('lets a type answer a submission itself', function () {
    $form = Form::create(['title' => 'Bedankt', 'template' => 'thanking']);

    $this->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['naam' => 'Jan'])
        ->assertOk()
        ->assertSee('Dank je, Jan');
});

it('starts the fields an editor built and the ones after them on a new row', function () {
    config(['filament-form-builder.types.half_row' => HalfRowForm::class]);

    $form = Form::create(['title' => 'Halve rij', 'template' => 'half_row', 'custom' => ['fields' => [
        ['type' => 'text', 'label' => 'Plaats', 'key' => 'plaats', 'column_span' => 6],
        ['type' => 'text', 'label' => 'Postcode', 'key' => 'postcode', 'column_span' => 6],
    ]]]);

    expect(array_map(fn (FormField $field): bool => $field->startsNewRow(), $form->getFields()))->toBe([false, true, false, true])
        ->and($form->getFields()[1]->getWrapperAttributes()->toHtml())
        ->toContain('data-form-builder-new-row="true"')
        ->toContain('--form-builder-column-start:1')
        ->and($form->getFields()[0]->getWrapperAttributes()->toHtml())->not->toContain('new-row');
});

it('stores the answers as the type prepared them for validation', function () {
    $form = Form::create(['title' => 'Postcode', 'template' => 'postcode']);

    $this->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['postcode' => '   '])
        ->assertSessionHasErrors('postcode');
    $this->post(route('filament-form-builder.form.store', ['formId' => $form->id]), ['postcode' => '1234 ab']);

    expect(FormSubmission::sole()->data)->toBe(['postcode' => '1234AB']);
});

it('leaves out a field an editor built when the type took its key in code', function () {
    Log::spy();

    $form = applicationForm([
        ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
        ['type' => 'text', 'label' => 'Bron', 'key' => 'ontvangen_via'],
        ['type' => 'textarea', 'label' => 'Motivatie', 'key' => 'motivatie'],
    ]);

    expect(keysOf($form->getFields(inputsOnly: true)))->toBe(['naam', 'vacature', 'motivatie', 'privacy'])
        ->and($form->getSubmissionFields()['naam'])->toBe('Naam');

    Log::shouldHaveReceived('warning')->twice();
});
