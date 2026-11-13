<?php

use VanOns\FilamentFormBuilder\Enums\ConditionOperator;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\NumberField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;

it('compares a single answer', function (ConditionOperator $operator, mixed $answer, bool $matches) {
    expect($operator->matches($answer, 'anders'))->toBe($matches);
})->with([
    [ConditionOperator::EQUALS, 'anders', true],
    [ConditionOperator::EQUALS, 'support', false],
    [ConditionOperator::NOT_EQUALS, 'support', true],
    [ConditionOperator::NOT_EQUALS, 'anders', false],
    [ConditionOperator::EMPTY, '', true],
    [ConditionOperator::EMPTY, null, true],
    [ConditionOperator::EMPTY, 'support', false],
    [ConditionOperator::NOT_EMPTY, 'support', true],
    [ConditionOperator::NOT_EMPTY, null, false],
]);

it('looks for the value among several answers', function (ConditionOperator $operator, array $answers, bool $matches) {
    expect($operator->matches($answers, 'seo'))->toBe($matches);
})->with([
    [ConditionOperator::EQUALS, ['web', 'seo'], true],
    [ConditionOperator::EQUALS, ['web'], false],
    [ConditionOperator::NOT_EQUALS, ['web'], true],
    [ConditionOperator::EMPTY, [], true],
    [ConditionOperator::NOT_EMPTY, ['web'], true],
]);

it('asks for a value only when it compares against one', function () {
    expect(ConditionOperator::EQUALS->needsValue())->toBeTrue()
        ->and(ConditionOperator::NOT_EQUALS->needsValue())->toBeTrue()
        ->and(ConditionOperator::EMPTY->needsValue())->toBeFalse()
        ->and(ConditionOperator::NOT_EMPTY->needsValue())->toBeFalse()
        ->and(ConditionOperator::AT_LEAST->needsValue())->toBeTrue();
});

it('compares numbers, and holds nothing without two', function (ConditionOperator $operator, mixed $answer, ?string $value, bool $matches) {
    expect($operator->matches($answer, $value))->toBe($matches);
})->with([
    [ConditionOperator::GREATER_THAN, '30', '24', true],
    [ConditionOperator::GREATER_THAN, '24', '24', false],
    [ConditionOperator::AT_LEAST, '24', '24', true],
    [ConditionOperator::LESS_THAN, 12, '24', true],
    [ConditionOperator::AT_MOST, '24.0', '24', true],
    [ConditionOperator::AT_MOST, null, '24', false],
    [ConditionOperator::AT_LEAST, 'abc', '0', false],
    [ConditionOperator::LESS_THAN, '12', null, false],
    [ConditionOperator::LESS_THAN, ['', '5'], '24', true],
]);

it('offers the comparisons only where the answer is a number', function () {
    $operators = fn (FormField $field): array => array_keys($field->getConditionOperators());

    expect($operators(new NumberField(['key' => 'uren', 'label' => 'Uren'])))->toContain('greater_than', 'at_most')
        ->and($operators(new TextInputField(['key' => 'naam', 'label' => 'Naam'])))->toBe(['equals', 'not_equals', 'empty', 'not_empty'])
        ->and($operators(new CheckboxField(['key' => 'akkoord', 'label' => 'Akkoord'])))->toBe(['not_empty', 'empty']);
});

it('offers every operator', function () {
    expect(ConditionOperator::options())->toHaveKeys(['equals', 'not_equals', 'empty', 'not_empty']);
});
