<?php

use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use Tests\Fixtures\ApplicationForm;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\RelationManagers\FormSubmissionsRelationManager;
use VanOns\FilamentFormBuilder\Filament\Tables\FormSubmissionColumns;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class BranchFilterField extends TextInputField
{
    public function getSubmissionColumns(): array
    {
        return [$this->getKey() => 'Vestiging', 'vestiging_plaats' => 'Vestiging plaats'];
    }
}

function filteredForm(): Form
{
    $form = Form::create([
        'title' => 'Solliciteren',
        'template' => 'custom',
        'custom' => ['fields' => [
            ['type' => 'text', 'label' => 'Naam', 'key' => 'naam'],
            ['type' => 'number', 'label' => 'Uren', 'key' => 'uren'],
            ['type' => 'date', 'label' => 'Start', 'key' => 'start'],
            ['type' => 'radio', 'label' => 'Aanhef', 'key' => 'aanhef', 'options' => [
                ['value' => 'dhr', 'label' => 'De heer'],
                ['value' => 'mw', 'label' => 'Mevrouw'],
            ]],
            ['type' => 'checkbox_list', 'label' => 'Interesses', 'key' => 'interesses', 'options' => [
                ['value' => 'web', 'label' => 'Websites'],
                ['value' => 'app', 'label' => 'Apps'],
            ]],
            ['type' => 'checkbox', 'label' => 'Privacy', 'key' => 'privacy'],
            ['type' => 'file_upload', 'label' => 'CV', 'key' => 'cv'],
        ]],
    ]);

    FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['naam' => 'Jan de Vries', 'uren' => '32', 'start' => '2026-11-01', 'aanhef' => 'dhr', 'interesses' => ['web', 'app'], 'privacy' => '1'],
        'files' => ['cv' => [['path' => 'form_uploads/cv.pdf', 'name' => 'cv.pdf']]],
    ]);
    FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['naam' => 'Anna', 'uren' => '8', 'start' => '2027-02-01', 'aanhef' => 'mw', 'interesses' => ['app']],
    ]);
    FormSubmission::create([
        'form_id' => $form->id,
        'data' => ['naam' => null, 'uren' => '', 'interesses' => []],
    ]);

    return $form;
}

/**
 * The names of the submissions one rule lets through, an empty name as "-".
 *
 * @return list<string>
 */
function matching(Form $form, string $rule, string $operator, array $settings = [], bool $inverse = false): array
{
    $constraint = FormSubmissionColumns::for($form)->constraints()[$rule];
    $query = FormSubmission::query()->where('form_id', $form->id);

    $constraint->getOperator($operator)
        ->constraint($constraint)
        ->settings($settings)
        ->inverse($inverse)
        ->applyToBaseQuery($query);

    return $query->orderBy('id')->get()->map(fn (FormSubmission $submission): string => $submission->data['naam'] ?? '-')->all();
}

it('finds text whatever its case, and an empty answer as not containing it', function () {
    $form = filteredForm();

    expect(matching($form, 'naam', 'contains', ['text' => 'VRIES']))->toBe(['Jan de Vries'])
        ->and(matching($form, 'naam', 'contains', ['text' => 'vries'], inverse: true))->toBe(['Anna', '-'])
        ->and(matching($form, 'naam', 'startsWith', ['text' => 'an']))->toBe(['Anna'])
        ->and(matching($form, 'naam', 'endsWith', ['text' => 'ries']))->toBe(['Jan de Vries'])
        ->and(matching($form, 'naam', 'equals', ['text' => 'anna']))->toBe(['Anna'])
        ->and(matching($form, 'naam', 'isFilled'))->toBe(['Jan de Vries', 'Anna'])
        ->and(matching($form, 'naam', 'isFilled', inverse: true))->toBe(['-']);
});

it('compares a number as a number', function () {
    $form = filteredForm();

    // As text, "8" would sort after "32".
    expect(matching($form, 'uren', 'isMin', ['number' => 10]))->toBe(['Jan de Vries'])
        ->and(matching($form, 'uren', 'isMax', ['number' => 10]))->toBe(['Anna'])
        ->and(matching($form, 'uren', 'isMin', ['number' => 10], inverse: true))->toBe(['Anna'])
        ->and(matching($form, 'uren', 'equals', ['number' => 8]))->toBe(['Anna'])
        ->and(matching($form, 'uren', 'isFilled', inverse: true))->toBe(['-']);
});

it('compares a date as a date', function () {
    $form = filteredForm();

    expect(matching($form, 'start', 'isAfter', ['date' => '2027-01-01']))->toBe(['Anna'])
        ->and(matching($form, 'start', 'isBefore', ['date' => '2027-01-01']))->toBe(['Jan de Vries'])
        ->and(matching($form, 'start', 'isDate', ['date' => '2026-11-01']))->toBe(['Jan de Vries'])
        ->and(matching($form, 'start', 'isFilled', inverse: true))->toBe(['-']);
});

it('picks a choice by its option', function () {
    $form = filteredForm();
    $constraint = FormSubmissionColumns::for($form)->constraints()['aanhef'];

    expect($constraint->getOptions())->toBe(['dhr' => 'De heer', 'mw' => 'Mevrouw'])
        ->and(matching($form, 'aanhef', 'is', ['values' => ['mw']]))->toBe(['Anna'])
        ->and(matching($form, 'aanhef', 'is', ['values' => ['mw']], inverse: true))->toBe(['Jan de Vries', '-']);
});

it('looks inside a list of choices', function () {
    $form = filteredForm();

    expect(matching($form, 'interesses', 'containsAny', ['values' => ['web']]))->toBe(['Jan de Vries'])
        ->and(matching($form, 'interesses', 'containsAny', ['values' => ['web', 'app']]))->toBe(['Jan de Vries', 'Anna'])
        ->and(matching($form, 'interesses', 'containsAny', ['values' => ['web']], inverse: true))->toBe(['Anna', '-'])
        ->and(matching($form, 'interesses', 'isFilled', inverse: true))->toBe(['-']);
});

it('tells a ticked checkbox and an uploaded file apart from none', function () {
    $form = filteredForm();

    expect(matching($form, 'privacy', 'isChecked'))->toBe(['Jan de Vries'])
        ->and(matching($form, 'privacy', 'isChecked', inverse: true))->toBe(['Anna', '-'])
        ->and(matching($form, 'cv', 'isFilled'))->toBe(['Jan de Vries'])
        ->and(matching($form, 'cv', 'isFilled', inverse: true))->toBe(['Anna', '-']);
});

it('offers a rule per column, per value the type adds and for the date', function () {
    config(['filament-form-builder.types.application' => ApplicationForm::class]);

    $constraints = FormSubmissionColumns::for(Form::create(['title' => 'Vacature', 'template' => 'application']))->constraints();

    expect(array_keys($constraints))->toBe(['naam', 'vacature', 'privacy', 'ontvangen_via', 'submitted_from', 'utm_source', 'utm_medium', 'utm_campaign', 'submitted_at'])
        ->and($constraints['ontvangen_via']->getLabel())->toBe('Ontvangen via')
        ->and($constraints['submitted_at'])->toBeInstanceOf(DateConstraint::class)
        ->and(array_keys((new BranchFilterField(['key' => 'vestiging']))->getFilterConstraints()))->toHaveCount(2);
});

it('finds submissions by the page they came from and its campaign', function () {
    $form = filteredForm();
    [$jan, $anna] = FormSubmission::query()->orderBy('id')->get();
    $jan->update(['source_url' => 'https://fonk.nl/vacatures/adviseur', 'meta' => ['campaign' => ['source' => 'LinkedIn', 'medium' => 'social']]]);
    $anna->update(['source_url' => 'https://fonk.nl/contact', 'meta' => ['campaign' => ['source' => 'nieuwsbrief']]]);

    expect(matching($form, 'submitted_from', 'contains', ['text' => '/vacatures/']))->toBe(['Jan de Vries'])
        ->and(matching($form, 'utm_source', 'equals', ['text' => 'linkedin']))->toBe(['Jan de Vries'])
        ->and(matching($form, 'utm_medium', 'isFilled', inverse: true))->toBe(['Anna', '-']);

    config(['filament-form-builder.submission_meta.campaign' => false]);

    expect(FormSubmissionColumns::for($form)->constraints())->not->toHaveKey('utm_source');
});

it('allows groups joined by OR, but no OR inside an OR', function () {
    $filter = FormSubmissionColumns::for(filteredForm())->filters()[0];
    $rule = ['type' => 'naam', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'jan']]];
    $or = fn (array ...$groups): array => ['type' => 'or', 'data' => ['groups' => array_map(fn (array $rules): array => ['rules' => $rules], $groups)]];

    expect($filter->exceedsRuleLimits([$or([$rule], [$rule], [$rule])]))->toBeFalse()
        ->and($filter->exceedsRuleLimits([$or([$or([$rule], [$rule])], [$rule])]))->toBeTrue();
});

it('filters the submissions of a form in its table', function () {
    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    $form = filteredForm();
    [$jan, $anna] = FormSubmission::query()->orderBy('id')->get();

    Livewire::test(FormSubmissionsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditForm::class])
        ->filterTable('queryBuilder', ['rules' => [
            'rule' => ['type' => 'aanhef', 'data' => ['operator' => 'is', 'settings' => ['values' => ['mw']]]],
        ]])
        ->assertCanSeeTableRecords([$anna])
        ->assertCanNotSeeTableRecords([$jan]);
});
