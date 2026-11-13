<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use VanOns\FilamentFormBuilder\Enums\ConditionOperator;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\ConditionsEditor;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\DateField;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use VanOns\FilamentFormBuilder\Models\Form;

it('reads a date as a date and leaves anything else alone', function () {
    $field = new DateField(['key' => 'start', 'label' => 'Start']);

    expect($field->formatSubmissionValue('2026-10-07'))->toBe('7 October 2026')
        ->and($field->formatSubmissionValue('morgen'))->toBe('morgen');
});

it('writes a date in the format the editor picked', function (?string $format, string $shown) {
    expect((new DateField(['key' => 'start', 'format' => $format]))->formatSubmissionValue('2026-10-07'))->toBe($shown);
})->with([
    ['d-m-Y', '07-10-2026'],
    ['j M Y', '7 Oct 2026'],
    ['Y-m-d', '2026-10-07'],
    ['H:i', '7 October 2026'],
    [null, '7 October 2026'],
]);

it('compares dates the way a date input sends them', function (ConditionOperator $operator, ?string $answer, bool $matches) {
    expect($operator->matches($answer, '2027-01-01'))->toBe($matches);
})->with([
    [ConditionOperator::LESS_THAN, '2026-12-31', true],
    [ConditionOperator::LESS_THAN, '2027-01-01', false],
    [ConditionOperator::AT_LEAST, '2027-01-01', true],
    [ConditionOperator::GREATER_THAN, '2027-01-02', true],
    [ConditionOperator::AT_MOST, null, false],
    [ConditionOperator::GREATER_THAN, '42', false],
]);

it('puts a rule on a date in words about dates', function () {
    $editor = new ConditionsEditor(['start' => new DateField(['key' => 'start', 'label' => 'Start'])]);

    expect(array_values((new DateField())->getConditionOperators()))->toBe(['on', 'not on', 'before', 'on or before', 'after', 'on or after', 'empty', 'not empty'])
        ->and($editor->describe([['key' => 'start', 'operator' => 'less_than', 'value' => '2027-01-01']], 'all'))->toBe('This field shows when Start is before 1 January 2027.')
        ->and($editor->badge([['key' => 'start', 'operator' => 'at_least', 'value' => '2027-01-01']], 'all'))->toBe('If Start on or after 1 January 2027');
});

it('shows a date on the canvas in its format', function () {
    test()->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret']));

    $form = Form::create(['title' => 'Inschrijven', 'template' => 'custom', 'custom' => ['fields' => [
        ['type' => 'date', 'label' => 'Startdatum', 'key' => 'start', 'format' => 'd-m-Y'],
    ]]]);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertSee('Startdatum')
        ->assertSee(now()->format('d-m-Y'));
});

/**
 * @return list<string>
 */
function dateErrors(DateField $field, mixed $value): array
{
    return Validator::make(['datum' => $value], $field->getRules(), attributes: ['datum' => 'Datum'])->errors()->all();
}

it('keeps a date between its earliest and its latest, and names them as the field shows dates', function () {
    $field = DateField::make('datum')->minDate('2026-10-07')->maxDate('2026-10-31');

    expect(dateErrors($field, '2026-10-07'))->toBe([])
        ->and(dateErrors($field, '2026-10-31'))->toBe([])
        ->and(dateErrors($field, '2026-10-06'))->toBe(['The Datum field may not be before 7 October 2026.'])
        ->and(dateErrors($field, '2026-11-01'))->toBe(['The Datum field may not be after 31 October 2026.']);
});

it('takes today as the earliest or the latest date', function () {
    $this->travelTo('2026-11-13 10:00');

    $appointment = DateField::make('datum')->notInPast();
    $birthday = DateField::make('datum')->notInFuture();

    expect(dateErrors($appointment, '2026-11-13'))->toBe([])
        ->and(dateErrors($appointment, '2026-11-12'))->toBe(['The Datum field may not be before 13 November 2026.'])
        ->and(dateErrors($birthday, '2026-11-13'))->toBe([])
        ->and(dateErrors($birthday, '2026-11-14'))->toBe(['The Datum field may not be after 13 November 2026.']);
});

it('takes the stricter of a fixed date and today', function () {
    $this->travelTo('2026-11-13 10:00');

    expect(DateField::make('datum')->minDate('2026-01-01')->notInPast()->getMinDate())->toBe('2026-11-13')
        ->and(DateField::make('datum')->minDate('2027-01-01')->notInPast()->getMinDate())->toBe('2027-01-01')
        ->and(DateField::make('datum')->maxDate('2027-01-01')->notInFuture()->getMaxDate())->toBe('2026-11-13');
});

it('reads the limits the canvas stores and ignores one that is no date', function () {
    $field = new DateField(['key' => 'datum', 'minDate' => '2026-10-07 00:00:00', 'maxDate' => 'later', 'notInPast' => false]);

    expect($field->getMinDate())->toBe('2026-10-07')
        ->and($field->getMaxDate())->toBeNull()
        ->and((new DateField(['key' => 'datum']))->getRules())->toBe(['datum' => ['nullable', 'string', 'max:1000', 'date_format:Y-m-d']]);
});

it('lets an optional date stay empty whatever its limits', function () {
    expect(dateErrors(DateField::make('datum')->minDate('2026-10-07'), null))->toBe([]);
});

it('hands the date input the limits the server checks', function () {
    view()->share('errors', new ViewErrorBag());

    expect((string) DateField::make('datum')->minDate('2026-10-07')->maxDate('2026-10-31')->render())
        ->toContain('min="2026-10-07"')
        ->toContain('max="2026-10-31"')
        ->and((string) DateField::make('datum')->render())->not->toContain('min=')->not->toContain('max=');
});
