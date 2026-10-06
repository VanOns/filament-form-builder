<?php

use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FileUploadField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;

it('drops the required rule of a field the visitor never sees', function () {
    $field = new TextInputField(['key' => 'bron', 'required' => true, 'hidden' => true]);

    expect($field->getRules())->toBe(['bron' => ['nullable']]);
});

it('keeps the required rule of a visible field', function () {
    $field = new TextInputField(['key' => 'bron', 'required' => true]);

    expect($field->getRules())->toBe(['bron' => ['required']]);
});

it('never hides a field that has no value to send on its own', function (string $type) {
    expect((new $type(['hidden' => true]))->isHidden())->toBeFalse();
})->with([
    CheckboxField::class,
    FileUploadField::class,
]);
