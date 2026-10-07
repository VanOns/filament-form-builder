<?php

use Illuminate\Http\Request;
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

it('starts with the value from the page URL, and with its default without one', function (string $url, string $value) {
    app()->instance('request', Request::create($url));
    $field = (new TextInputField(['key' => 'vacature', 'hidden' => true, 'defaultValue' => 'Open sollicitatie']))->defaultFromQuery('vacature');

    expect($field->getInitialValue())->toBe($value)
        ->and($field->getDefaultValue())->toBe('Open sollicitatie')
        ->and((string) $field->render())->toContain('value="' . $value . '"');
})->with([
    ['/werken-bij?vacature=Senior%20adviseur', 'Senior adviseur'],
    ['/werken-bij', 'Open sollicitatie'],
    ['/werken-bij?vacature=', 'Open sollicitatie'],
    ['/werken-bij?vacature[]=a', 'Open sollicitatie'],
]);
