<?php

use Illuminate\Support\Facades\Process;

/**
 * Runs exports of resources/js/steps.js in Node; an argument `['set' => [...]]`
 * goes in as a Set, the way hiddenKeys() hands the hidden keys over.
 *
 * @param  list<array{0: string, 1: list<mixed>}>  $calls
 * @return list<mixed>
 */
function inStepsScript(array $calls): array
{
    $module = 'file://' . realpath(__DIR__ . '/../../../resources/js/steps.js');
    $script = <<<JS
        import * as steps from '{$module}';
        let input = '';
        for await (const chunk of process.stdin) input += chunk;
        const results = JSON.parse(input).map(([name, args]) => steps[name](...args.map((arg) => arg?.set ? new Set(arg.set) : arg)));
        console.log(JSON.stringify(results));
        JS;

    $result = Process::input(json_encode($calls))->run(['node', '--input-type=module', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    return json_decode($result->output(), true);
}

function threeSteps(): array
{
    return [
        ['title' => 'Over jou', 'keys' => ['naam']],
        ['title' => 'Bedrijf', 'keys' => ['bedrijf', 'kvk']],
        ['title' => 'Afronden', 'keys' => []],
    ];
}

it('skips a step whose fields the conditions all hide, but never one with only text', function () {
    $hidden = ['set' => ['bedrijf', 'kvk']];

    expect(inStepsScript([
        ['visibleSteps', [threeSteps(), ['set' => []]]],
        ['visibleSteps', [threeSteps(), $hidden]],
        ['visibleSteps', [threeSteps(), ['set' => ['bedrijf']]]],
        ['nextStep', [threeSteps(), $hidden, 0]],
        ['previousStep', [threeSteps(), $hidden, 2]],
        ['nextStep', [threeSteps(), $hidden, 2]],
        ['previousStep', [threeSteps(), $hidden, 0]],
    ]))->toBe([[0, 1, 2], [0, 2], [0, 1, 2], 2, 0, null, null]);
});

it('finds the step of an error, also on one file of several', function () {
    expect(inStepsScript([
        ['stepOf', [threeSteps(), 'kvk']],
        ['stepOf', [threeSteps(), 'onbekend']],
        ['firstStepWithError', [threeSteps(), ['kvk', 'naam.0']]],
        ['firstStepWithError', [threeSteps(), ['cf-turnstile-response']]],
    ]))->toBe([1, null, 0, null]);
});

it('counts the progress among the steps that show', function () {
    expect(inStepsScript([
        ['progress', [threeSteps(), ['set' => []], 1]],
        ['progress', [threeSteps(), ['set' => ['bedrijf', 'kvk']], 2]],
    ]))->toBe([
        ['number' => 2, 'total' => 3, 'percent' => 67],
        ['number' => 2, 'total' => 2, 'percent' => 100],
    ]);
});
