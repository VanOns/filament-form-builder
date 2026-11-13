<?php

use VanOns\FilamentFormBuilder\Enums\FieldWidth;
use VanOns\FilamentFormBuilder\Enums\GridLayout;

it('fits at most four fields on a row', function () {
    expect(FieldWidth::FULL->value / FieldWidth::QUARTER->value)->toBe(4);
});

it('reads an empty span as the full width and keeps a span to a width the row can hold', function () {
    expect(FieldWidth::fit(null))->toBe(FieldWidth::FULL)
        ->and(FieldWidth::fit('8'))->toBe(FieldWidth::TWO_THIRDS)
        ->and(FieldWidth::fit(5))->toBe(FieldWidth::THIRD)
        ->and(FieldWidth::fit(1))->toBe(FieldWidth::QUARTER)
        ->and(FieldWidth::fit(3, FieldWidth::THIRD))->toBe(FieldWidth::THIRD);
});

it('offers every width by default', function () {
    expect(GridLayout::current())->toBe(GridLayout::FLEXIBLE)
        ->and(FieldWidth::available())->toBe(FieldWidth::cases());
});

it('offers only halves and full rows in two columns', function () {
    config(['filament-form-builder.layout' => 'two_columns']);

    expect(FieldWidth::available())->toBe([FieldWidth::HALF, FieldWidth::FULL])
        ->and(FieldWidth::fit(4))->toBe(FieldWidth::HALF)
        ->and(FieldWidth::fit(9))->toBe(FieldWidth::HALF)
        ->and(FieldWidth::within(4))->toBeNull();
});

it('puts every field on a row of its own in full width', function () {
    config(['filament-form-builder.layout' => GridLayout::FULL_WIDTH]);

    expect(FieldWidth::available())->toBe([FieldWidth::FULL])
        ->and(FieldWidth::fit(6))->toBe(FieldWidth::FULL);
});

it('falls back to every width for a layout it does not know', function () {
    config(['filament-form-builder.layout' => 'three_columns']);

    expect(GridLayout::current())->toBe(GridLayout::FLEXIBLE);
});
