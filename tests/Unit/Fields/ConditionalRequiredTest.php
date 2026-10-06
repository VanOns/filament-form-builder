<?php

use Illuminate\Support\Facades\Validator;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField;

/**
 * Whether an empty "toelichting" passes for the given answer to "onderwerp".
 */
function emptyExplanationPasses(string $type, ?string $value, ?string $onderwerp): bool
{
    $field = new TextAreaField([
        'key' => 'toelichting',
        'required' => true,
        'visibleWhenKey' => 'onderwerp',
        'visibleWhenType' => $type,
        'visibleWhenValue' => $value,
    ]);

    return Validator::make(['onderwerp' => $onderwerp, 'toelichting' => ''], $field->getRules())->passes();
}

it('is only required while its condition shows it', function (string $type, ?string $value, ?string $shown, ?string $hidden) {
    expect(emptyExplanationPasses($type, $value, $shown))->toBeFalse()
        ->and(emptyExplanationPasses($type, $value, $hidden))->toBeTrue();
})->with([
    'equals' => ['equals', 'anders', 'anders', 'support'],
    'not equals' => ['not_equals', 'anders', 'support', 'anders'],
    'empty' => ['empty', null, null, 'support'],
    'not empty' => ['not_empty', null, 'support', null],
]);
