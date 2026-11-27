<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextInputField;

/**
 * @return list<string>
 */
function lengthErrors(FormField $field, mixed $value): array
{
    return Validator::make(['antwoord' => $value], $field->getRules(), attributes: ['antwoord' => 'Antwoord'])->errors()->all();
}

it('keeps a text between its minimum and maximum number of characters', function (string $type) {
    $field = $type::make('antwoord')->minLength(3)->maxLength(5);

    expect(lengthErrors($field, 'abc'))->toBe([])
        ->and(lengthErrors($field, 'abcde'))->toBe([])
        ->and(lengthErrors($field, 'ab'))->toBe(['The Antwoord field must be at least 3 characters.'])
        ->and(lengthErrors($field, 'abcdef'))->toBe(['The Antwoord field must not be greater than 5 characters.']);
})->with([TextInputField::class, TextAreaField::class]);

it('counts a line break as one character, as the browser does', function () {
    $field = TextAreaField::make('antwoord')->maxLength(5);

    expect(lengthErrors($field, "ab\r\ncd"))->toBe([])
        ->and(lengthErrors($field, "ab\r\ncde"))->not->toBe([]);
});

it('lets an optional text stay empty whatever its minimum', function () {
    expect(lengthErrors(TextInputField::make('antwoord')->minLength(10), null))->toBe([]);
});

it('never takes a text longer than the config allows', function () {
    config(['filament-form-builder.max_length.text' => 20]);
    $field = new TextInputField(['key' => 'antwoord', 'maxLength' => '50']);

    expect($field->getMaxLength())->toBe(20)
        ->and(lengthErrors($field, str_repeat('a', 21)))->not->toBe([]);
});

it('leaves the rules of a text without a length of its own as they were', function () {
    expect(TextInputField::make('antwoord')->getRules())->toBe(['antwoord' => ['nullable', 'string', 'max:1000']])
        ->and(TextAreaField::make('antwoord')->getRules())->toBe(['antwoord' => ['nullable', 'string', 'max:20000']])
        ->and((new TextInputField(['key' => 'antwoord', 'minLength' => '', 'maxLength' => 'veel']))->getRules())->toBe(['antwoord' => ['nullable', 'string', 'max:1000']]);
});

it('hands the browser the lengths the server checks', function (string $type) {
    view()->share('errors', new ViewErrorBag());

    expect((string) $type::make('antwoord')->minLength(3)->maxLength(50)->render())->toContain('minlength="3"')->toContain('maxlength="50"')
        ->and((string) $type::make('antwoord')->render())->not->toContain('minlength=')->not->toContain('maxlength=');
})->with([TextInputField::class, TextAreaField::class]);
