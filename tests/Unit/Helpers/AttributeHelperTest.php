<?php

use VanOns\FilamentFormBuilder\Helpers\AttributeHelper;

it('returns an empty string for an empty array', function () {
    expect(AttributeHelper::arrayToString([]))->toBe('');
});

it('converts a single attribute to a key=value string', function () {
    expect(AttributeHelper::arrayToString(['enctype' => 'multipart/form-data']))->toBe('enctype=multipart/form-data');
});

it('converts multiple attributes separated by spaces', function () {
    $result = AttributeHelper::arrayToString([
        'enctype' => 'multipart/form-data',
        'data-form-builder-form' => '42',
    ]);

    expect($result)->toBe('enctype=multipart/form-data data-form-builder-form=42');
});

it('trims trailing whitespace from the result', function () {
    $result = AttributeHelper::arrayToString(['foo' => 'bar']);

    expect($result)->not->toEndWith(' ');
});
