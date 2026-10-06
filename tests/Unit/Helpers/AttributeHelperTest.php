<?php

use VanOns\FilamentFormBuilder\Helpers\AttributeHelper;

it('renders nothing for no attributes', function () {
    expect(AttributeHelper::render([])->toHtml())->toBe('');
});

it('renders quoted attributes separated by spaces', function () {
    $html = AttributeHelper::render([
        'enctype' => 'multipart/form-data',
        'data-form-builder-form' => 42,
    ])->toHtml();

    expect($html)->toBe('enctype="multipart/form-data" data-form-builder-form="42"');
});

it('keeps a value with spaces inside its attribute', function () {
    expect(AttributeHelper::render(['data-visible-when-value' => 'Ja graag'])->toHtml())
        ->toBe('data-visible-when-value="Ja graag"');
});

it('escapes a value so it cannot break out of the attribute', function () {
    $html = AttributeHelper::render(['data-visible-when-value' => '"><script>alert(1)</script>'])->toHtml();

    expect($html)->not->toContain('<script>')
        ->and($html)->toBe('data-visible-when-value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"');
});
