<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;

function field(array $data = [], int $columns = 3): TextInputField
{
    return (new TextInputField(['key' => 'voornaam', 'label' => 'Voornaam', ...$data]))
        ->setGridColumns($columns);
}

it('places a field on the grid through its wrapper', function () {
    $attributes = field(['column_span' => 2])->getWrapperAttributes()->toHtml();

    expect($attributes)->toContain('data-form-builder-column-span="2"')
        ->and($attributes)->toContain('--form-builder-column-span:2');
});

it('falls back to a single column when nothing is stored', function () {
    $attributes = field()->getWrapperAttributes()->toHtml();

    expect($attributes)->toContain('data-form-builder-column-span="1"');
});

it('never spans more columns than the form has', function () {
    // Stored on a wider form, then the form type dropped to 3 columns.
    $attributes = field(['column_span' => 4])->getWrapperAttributes()->toHtml();

    expect($attributes)->toContain('data-form-builder-column-span="3"');
});

it('keeps the existing wrapper key attribute', function () {
    expect(field()->getWrapperAttributes()->toHtml())->toContain('data-form-builder-input-wrapper="voornaam"');
});
