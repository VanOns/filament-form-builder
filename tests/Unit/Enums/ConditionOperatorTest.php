<?php

use VanOns\FilamentFormBuilder\Enums\ConditionOperator;

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
        ->and(ConditionOperator::NOT_EMPTY->needsValue())->toBeFalse();
});

it('offers every operator', function () {
    expect(ConditionOperator::options())->toHaveKeys(['equals', 'not_equals', 'empty', 'not_empty']);
});
