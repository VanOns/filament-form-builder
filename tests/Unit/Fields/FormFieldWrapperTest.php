<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\InputField;

function field(array $data = [], int $columns = 3): InputField
{
    return (new InputField(['key' => 'voornaam', 'label' => 'Voornaam', ...$data]))
        ->setGridColumns($columns);
}

it('places a field on the grid through its wrapper', function () {
    $attributes = field(['column_span' => 2, 'column_start' => 2])->getWrapperAttributes();

    expect($attributes)->toContain('data-form-builder-column-span=2')
        ->and($attributes)->toContain('data-form-builder-column-start=2')
        ->and($attributes)->toContain('--form-builder-column-span:2')
        ->and($attributes)->toContain('--form-builder-column-start:2');
});

it('leaves out the start column when the field places itself', function () {
    $attributes = field(['column_span' => 2])->getWrapperAttributes();

    expect($attributes)->not->toContain('column-start')
        ->and($attributes)->toContain('data-form-builder-column-span=2');
});

it('spans the whole row for a full width field', function () {
    $attributes = field(['large' => true], columns: 4)->getWrapperAttributes();

    expect($attributes)->toContain('data-form-builder-column-span=4');
});

it('falls back to a single column when nothing is stored', function () {
    $attributes = field()->getWrapperAttributes();

    expect($attributes)->toContain('data-form-builder-column-span=1');
});

it('never spans past the last column', function () {
    // Stored on a wider form, then the template dropped to 3 columns.
    $attributes = field(['column_span' => 4, 'column_start' => 3])->getWrapperAttributes();

    expect($attributes)->toContain('data-form-builder-column-span=1');
});

it('keeps the existing wrapper key attribute', function () {
    expect(field()->getWrapperAttributes())->toContain('data-form-builder-input-wrapper=key_voornaam');
});
