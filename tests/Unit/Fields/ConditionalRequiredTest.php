<?php

use Illuminate\Support\Facades\Validator;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField;

/**
 * Whether an empty "toelichting" passes for the given answers.
 */
function emptyExplanationPasses(array $conditions, array $answers, string $match = 'all'): bool
{
    $field = new TextAreaField([
        'key' => 'toelichting',
        'required' => true,
        'conditions' => $conditions,
        'conditionMatch' => $match,
    ]);

    return Validator::make([...$answers, 'toelichting' => ''], $field->getRules())->passes();
}

it('is only required while its condition shows it', function (string $operator, ?string $value, ?string $shown, ?string $hidden) {
    $conditions = [['key' => 'onderwerp', 'operator' => $operator, 'value' => $value]];

    expect(emptyExplanationPasses($conditions, ['onderwerp' => $shown]))->toBeFalse()
        ->and(emptyExplanationPasses($conditions, ['onderwerp' => $hidden]))->toBeTrue();
})->with([
    'equals' => ['equals', 'anders', 'anders', 'support'],
    'not equals' => ['not_equals', 'anders', 'support', 'anders'],
    'empty' => ['empty', null, null, 'support'],
    'not empty' => ['not_empty', null, 'support', null],
]);

it('follows a condition on a multiple choice field', function () {
    $conditions = [['key' => 'interesses', 'operator' => 'equals', 'value' => 'seo']];

    expect(emptyExplanationPasses($conditions, ['interesses' => ['web', 'seo']]))->toBeFalse()
        ->and(emptyExplanationPasses($conditions, ['interesses' => ['web']]))->toBeTrue();
});

it('needs every condition to hold when they all have to', function () {
    $conditions = [
        ['key' => 'onderwerp', 'operator' => 'equals', 'value' => 'anders'],
        ['key' => 'land', 'operator' => 'equals', 'value' => 'nl'],
    ];

    expect(emptyExplanationPasses($conditions, ['onderwerp' => 'anders', 'land' => 'nl']))->toBeFalse()
        ->and(emptyExplanationPasses($conditions, ['onderwerp' => 'anders', 'land' => 'be']))->toBeTrue();
});

it('needs one condition to hold when any will do', function () {
    $conditions = [
        ['key' => 'onderwerp', 'operator' => 'equals', 'value' => 'anders'],
        ['key' => 'land', 'operator' => 'equals', 'value' => 'nl'],
    ];

    expect(emptyExplanationPasses($conditions, ['onderwerp' => 'support', 'land' => 'nl'], 'any'))->toBeFalse()
        ->and(emptyExplanationPasses($conditions, ['onderwerp' => 'support', 'land' => 'be'], 'any'))->toBeTrue();
});

it('hands the conditions to the frontend in one attribute', function () {
    $field = new TextAreaField([
        'key' => 'toelichting',
        'conditions' => ['a' => ['key' => 'onderwerp', 'operator' => 'equals', 'value' => 'iets "anders"']],
    ]);

    expect($field->getAttributes()->toHtml())
        ->toContain('data-conditions="{&quot;match&quot;:&quot;all&quot;,&quot;rules&quot;:[{&quot;key&quot;:&quot;onderwerp&quot;,&quot;operator&quot;:&quot;equals&quot;,&quot;value&quot;:&quot;iets \\&quot;anders\\&quot;&quot;}]}"');
});

it('ignores a condition that names no field', function () {
    $field = new TextAreaField(['key' => 'toelichting', 'conditions' => [['operator' => 'equals', 'value' => 'x']]]);

    expect($field->hasConditions())->toBeFalse();
});
