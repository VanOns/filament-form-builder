<?php

use VanOns\FilamentFormBuilder\Enums\VisibilityType;

it('formatString returns the value as-is for EQUALS', function () {
    expect(VisibilityType::EQUALS->formatString('foo'))->toBe('foo');
});

it('formatString prefixes __not__ for NOT_EQUALS', function () {
    expect(VisibilityType::NOT_EQUALS->formatString('foo'))->toBe('__not__foo');
});

it('formatString returns __empty__ for EMPTY regardless of input', function () {
    expect(VisibilityType::EMPTY->formatString('anything'))->toBe('__empty__');
});

it('formatString returns __not_empty__ for NOT_EMPTY regardless of input', function () {
    expect(VisibilityType::NOT_EMPTY->formatString('anything'))->toBe('__not_empty__');
});

it('formatString treats null as empty string for EQUALS', function () {
    expect(VisibilityType::EQUALS->formatString(null))->toBe('');
});

it('formatString treats null as empty string for NOT_EQUALS', function () {
    expect(VisibilityType::NOT_EQUALS->formatString(null))->toBe('__not__');
});

it('getRequiredRule returns null when key is null', function () {
    expect(VisibilityType::EQUALS->getRequiredRule(null, 'foo'))->toBeNull();
});

it('getRequiredRule returns required_if for EQUALS', function () {
    expect(VisibilityType::EQUALS->getRequiredRule('field', 'yes'))->toBe('required_if:field,yes');
});

it('getRequiredRule returns required_unless for NOT_EQUALS', function () {
    expect(VisibilityType::NOT_EQUALS->getRequiredRule('field', 'yes'))->toBe('required_unless:field,yes');
});

it('getRequiredRule returns required_if with empty value for EMPTY', function () {
    expect(VisibilityType::EMPTY->getRequiredRule('field', null))->toBe('required_if:field,');
});

it('getRequiredRule returns required_unless with empty value for NOT_EMPTY', function () {
    expect(VisibilityType::NOT_EMPTY->getRequiredRule('field', null))->toBe('required_unless:field,');
});

it('toArray returns all four cases as value => label pairs', function () {
    $result = VisibilityType::toArray();

    expect($result)->toHaveKeys(['equals', 'not_equals', 'empty', 'not_empty'])
        ->and($result)->toHaveCount(4);
});
