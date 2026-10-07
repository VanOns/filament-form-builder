<?php

use Illuminate\Support\Facades\Process;
use VanOns\FilamentFormBuilder\Classes\FieldConditions;
use VanOns\FilamentFormBuilder\Enums\ConditionOperator;

/**
 * Runs an export of resources/js/conditions.js in Node on the given arguments.
 *
 * @param  list<list<mixed>>  $calls
 * @return list<mixed>
 */
function inJavaScript(string $function, array $calls): array
{
    $module = 'file://' . realpath(__DIR__ . '/../../../resources/js/conditions.js');
    $script = <<<JS
        import * as conditions from '{$module}';
        let input = '';
        for await (const chunk of process.stdin) input += chunk;
        const results = JSON.parse(input).map((args) => conditions.{$function}(...args));
        console.log(JSON.stringify(results, (key, value) => value instanceof Set ? [...value].sort() : value));
        JS;

    $result = Process::input(json_encode($calls))->run(['node', '--input-type=module', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    return json_decode($result->output(), true);
}

beforeEach(function () {
    if (! Process::run(['node', '--version'])->successful()) {
        $this->markTestSkipped('Node is not installed.');
    }
});

it('reads every rule in JavaScript the way the server does', function () {
    $values = [null, '', '5', '10', ' 7 ', '1e3', '0x1A', 'abc', '2026-10-07', '2027-01-01', ['web', 'app'], [], ['5', '9']];
    $expected = [null, '', '5', '7', '1000', '1', '2026-12-31', 'web', 'abc'];
    $calls = [];

    foreach ($values as $value) {
        foreach (ConditionOperator::cases() as $operator) {
            foreach ($expected as $wanted) {
                $calls[] = [$value, $operator->value, $wanted];
            }
        }
    }

    $server = array_map(fn (array $call): bool => ConditionOperator::from($call[1])->matches($call[0], $call[2]), $calls);

    expect(inJavaScript('matches', $calls))->toBe($server);
});

it('combines rules in JavaScript the way the server does', function () {
    $rules = [
        ['key' => 'onderwerp', 'operator' => 'equals', 'value' => 'anders'],
        ['key' => 'uren', 'operator' => 'at_least', 'value' => '24'],
        ['key' => 'onbekend', 'operator' => 'contains', 'value' => 'x'],
    ];
    $answers = [[], ['onderwerp' => 'anders'], ['uren' => '32'], ['onderwerp' => 'anders', 'uren' => '8']];
    $calls = [];
    $server = [];

    foreach (['all', 'any'] as $match) {
        foreach ($answers as $data) {
            $conditions = FieldConditions::fromArray($rules, $match);
            $calls[] = [$conditions->toArray(), $data];
            $server[] = $conditions->passes($data);
        }
    }

    expect(inJavaScript('passes', $calls))->toBe($server);
});

it('hides a field that depends on a hidden one', function () {
    $conditions = [
        'toelichting' => ['match' => 'all', 'rules' => [['key' => 'onderwerp', 'operator' => 'equals', 'value' => 'anders']]],
        'bijlage' => ['match' => 'all', 'rules' => [['key' => 'toelichting', 'operator' => 'not_empty', 'value' => null]]],
    ];

    expect(inJavaScript('hiddenKeys', [
        [$conditions, ['onderwerp' => 'anders', 'toelichting' => 'Een app']],
        [$conditions, ['onderwerp' => 'website', 'toelichting' => 'Een app']],
    ]))->toBe([[], ['bijlage', 'toelichting']]);
});
