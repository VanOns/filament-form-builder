<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\NumberField;
use VanOns\FilamentFormBuilder\Rules\MaxDecimals;

function numberPasses(NumberField $field, mixed $value): bool
{
    return Validator::make(['aantal' => $value], $field->getRules())->passes();
}

it('takes whole numbers only until it is given decimals', function () {
    $whole = NumberField::make('aantal');
    $twoDecimals = NumberField::make('aantal')->decimals(2);

    expect(numberPasses($whole, '42'))->toBeTrue()
        ->and(numberPasses($whole, '-3'))->toBeTrue()
        ->and(numberPasses($whole, '4.5'))->toBeFalse()
        ->and(numberPasses($twoDecimals, '42'))->toBeTrue()
        ->and(numberPasses($twoDecimals, '4.5'))->toBeTrue()
        ->and(numberPasses($twoDecimals, '4.25'))->toBeTrue()
        ->and(numberPasses($twoDecimals, '4.255'))->toBeFalse();
});

it('keeps an answer between its minimum and its maximum', function () {
    $field = NumberField::make('aantal')->min(1)->max(10);

    expect(numberPasses($field, '1'))->toBeTrue()
        ->and(numberPasses($field, '10'))->toBeTrue()
        ->and(numberPasses($field, '0'))->toBeFalse()
        ->and(numberPasses($field, '11'))->toBeFalse();
});

it('takes a minimum or a maximum on its own', function () {
    $atLeast = NumberField::make('aantal')->min(18);
    $atMost = NumberField::make('aantal')->max(0.5)->decimals(1);

    expect(numberPasses($atLeast, '17'))->toBeFalse()
        ->and(numberPasses($atLeast, '5000'))->toBeTrue()
        ->and(numberPasses($atMost, '0.6'))->toBeFalse()
        ->and(numberPasses($atMost, '-20'))->toBeTrue();
});

it('lets an optional number stay empty whatever its limits', function () {
    expect(numberPasses(NumberField::make('aantal')->min(5), null))->toBeTrue();
});

it('reads the limits the canvas stores the same as the ones set in code', function () {
    $fromCanvas = new NumberField(['key' => 'aantal', 'min' => '1', 'max' => '2.5', 'decimals' => '2']);
    $inCode = NumberField::make('aantal')->min(1)->max(2.5)->decimals(2);

    expect($fromCanvas->getRules())->toEqual($inCode->getRules())
        ->and($fromCanvas->getRules())->toEqual(['aantal' => ['nullable', 'numeric', new MaxDecimals(2), 'min:1', 'max:2.5']]);
});

it('says in its own words how many decimals an answer may have', function () {
    $messages = fn (int $decimals, string $value): array => Validator::make(
        ['bedrag' => $value],
        NumberField::make('bedrag')->label('Bedrag')->decimals($decimals)->getRules(),
        attributes: ['bedrag' => 'Bedrag'],
    )->errors()->all();

    expect($messages(2, '4.255'))->toBe(['The Bedrag field may have at most 2 decimals.'])
        ->and($messages(1, '4.25'))->toBe(['The Bedrag field may have at most 1 decimal.'])
        ->and($messages(2, '1e3'))->toBe(['The Bedrag field must be a number.']);
});

it('ignores a limit that is no number', function () {
    $field = new NumberField(['key' => 'aantal', 'min' => '', 'max' => 'veel', 'decimals' => null]);

    expect($field->getRules())->toBe(['aantal' => ['nullable', 'numeric', 'integer']]);
});

it('hands the browser the limits the server checks', function () {
    view()->share('errors', new ViewErrorBag());

    $limited = (string) NumberField::make('bedrag')->min(1)->max(99.95)->decimals(2)->render();
    $plain = (string) NumberField::make('aantal')->render();

    expect($limited)->toContain('min="1"')
        ->toContain('max="99.95"')
        ->toContain('step="0.01"')
        ->and($plain)->not->toContain('min=')
        ->not->toContain('max=')
        ->not->toContain('step=');
});
