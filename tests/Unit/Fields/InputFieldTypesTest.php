<?php

use Illuminate\Support\Facades\Validator;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\EmailField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\InputField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\NumberField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\PhoneField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;

/**
 * @param  class-string<InputField>  $type
 */
function passes(string $type, mixed $value): bool
{
    $field = new $type(['key' => 'answer', 'label' => 'Answer']);

    return Validator::make(['answer' => $value], array_filter($field->getRules()))->passes();
}

it('renders each type as its own HTML input type', function (string $type, string $inputType) {
    expect((new $type())->getInputType())->toBe($inputType);
})->with([
    [TextInputField::class, 'text'],
    [EmailField::class, 'email'],
    [PhoneField::class, 'tel'],
    [NumberField::class, 'number'],
]);

it('validates the value against its type', function (string $type, mixed $valid, mixed $invalid) {
    expect(passes($type, $valid))->toBeTrue()
        ->and(passes($type, $invalid))->toBeFalse();
})->with([
    [EmailField::class, 'jan@example.com', 'jan'],
    [PhoneField::class, '+31 (0)6 12-34 56 78', 'bel me'],
    [NumberField::class, '42', 'veel'],
]);

it('accepts any text in a text field', function () {
    expect(passes(TextInputField::class, 'Wat dan ook'))->toBeTrue();
});

it('lets an optional field stay empty', function (string $type) {
    // The browser posts an empty field as "", which Laravel turns into null.
    expect(passes($type, null))->toBeTrue();
})->with([
    TextInputField::class,
    EmailField::class,
    PhoneField::class,
    NumberField::class,
]);
