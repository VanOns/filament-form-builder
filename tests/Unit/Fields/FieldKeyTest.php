<?php

use Illuminate\Support\Facades\Validator;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;

it('derives the key from the label', function () {
    expect((new TextInputField(['label' => 'Voor naam']))->getKey())->toBe('voor_naam');
});

it('prefers a key that was set', function () {
    expect((new TextInputField(['label' => 'Voor naam', 'key' => 'voornaam']))->getKey())->toBe('voornaam');
});

it('rejects characters that change how the request is read', function (string $key) {
    $passes = Validator::make(['key' => $key], ['key' => TextInputField::getKeyValidationRule()])->passes();

    expect($passes)->toBeFalse();
})->with(['voor.naam', 'voor*naam', 'voor naam', 'naam[x]']);
