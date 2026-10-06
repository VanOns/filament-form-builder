<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxListField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\ChoiceField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\DropdownField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\RadioField;

/**
 * @param  class-string<ChoiceField>  $type
 */
function choice(string $type, array $data = []): ChoiceField
{
    return new $type([
        'key' => 'aanhef',
        'label' => 'Aanhef',
        'options' => [
            ['value' => 'dhr', 'label' => 'De heer'],
            ['value' => 'mevr', 'label' => 'Mevrouw'],
        ],
        ...$data,
    ]);
}

function choicePasses(ChoiceField $field, mixed $value): bool
{
    return Validator::make(['aanhef' => $value], $field->getRules())->passes();
}

it('accepts one of its own options', function (string $type) {
    expect(choicePasses(choice($type), 'mevr'))->toBeTrue()
        ->and(choicePasses(choice($type), 'dokter'))->toBeFalse();
})->with([RadioField::class, DropdownField::class]);

it('accepts several of its own options at once', function () {
    $field = choice(CheckboxListField::class);

    expect(choicePasses($field, ['dhr', 'mevr']))->toBeTrue()
        ->and(choicePasses($field, ['dhr', 'dokter']))->toBeFalse()
        ->and(choicePasses($field, 'dhr'))->toBeFalse();
});

it('renders radio buttons and checkboxes as real inputs', function (string $type, string $inputType) {
    view()->share('errors', new ViewErrorBag());

    expect(choice($type)->render())->toContain("type=\"{$inputType}\"");
})->with([
    [RadioField::class, 'radio'],
    [CheckboxListField::class, 'checkbox'],
]);

it('renders a dropdown as a select with an empty first option', function () {
    view()->share('errors', new ViewErrorBag());

    $html = choice(DropdownField::class, ['placeholder' => 'Kies…'])->render();

    expect($html)->toContain('<select')
        ->and($html)->toContain('<option value="">Kies…</option>');
});

it('lets an optional choice stay empty', function (string $type) {
    expect(choicePasses(choice($type), null))->toBeTrue();
})->with([RadioField::class, CheckboxListField::class, DropdownField::class]);
