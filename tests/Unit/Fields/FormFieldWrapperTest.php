<?php

use VanOns\FilamentFormBuilder\Enums\FieldWidth;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\RecaptchaField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;

function field(array $data = []): TextInputField
{
    return new TextInputField(['key' => 'voornaam', 'label' => 'Voornaam', ...$data]);
}

it('places a field on the grid through its wrapper', function () {
    $attributes = field(['column_span' => 4])->getWrapperAttributes()->toHtml();

    expect($attributes)->toContain('data-form-builder-column-span="4"')
        ->and($attributes)->toContain('--form-builder-column-span:4');
});

it('takes the full width when nothing is stored', function () {
    expect(field()->getWrapperAttributes()->toHtml())->toContain('data-form-builder-column-span="12"');
});

it('keeps a stored span to a width a row can hold', function () {
    expect(field(['column_span' => 5])->getColumnSpan())->toBe(4)
        ->and(field(['column_span' => 1])->getColumnSpan())->toBe(3)
        ->and(field(['column_span' => 20])->getColumnSpan())->toBe(12);
});

it('never reads a field narrower than its type works at', function () {
    expect((new TextAreaField(['key' => 'bericht', 'column_span' => 3]))->getWidth())->toBe(FieldWidth::THIRD)
        ->and((new RecaptchaField(['column_span' => 4]))->getColumnSpan())->toBe(6);
});

it('keeps the existing wrapper key attribute', function () {
    expect(field()->getWrapperAttributes()->toHtml())->toContain('data-form-builder-input-wrapper="voornaam"');
});
