<?php

use Illuminate\Support\Facades\Process;

beforeEach(function () {
    if (! Process::run(['node', '--version'])->successful()) {
        $this->markTestSkipped('Node is not installed.');
    }
});

it('hands a front end of its own the traps the server sets', function () {
    $module = 'file://' . realpath(__DIR__ . '/../../../resources/js/honeypot.js');
    $honeypot = json_encode(['field' => 'ffb_website', 'tokenField' => 'ffb_token', 'token' => 'encrypted']);
    $script = <<<JS
        import { honeypotInput, honeypotFields } from '{$module}';
        const honeypot = {$honeypot};
        console.log(JSON.stringify([honeypotInput(honeypot), honeypotFields(honeypot), honeypotFields(null)]));
        JS;

    $result = Process::run(['node', '--input-type=module', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    [$input, $fields, $none] = json_decode($result->output(), true);

    expect($input)->toMatchArray(['type' => 'text', 'name' => 'ffb_website', 'tabIndex' => -1, 'autoComplete' => 'off', 'aria-hidden' => 'true'])
        ->and($input['style']['left'])->toBe('-10000px')
        ->and($fields)->toBe(['ffb_website' => '', 'ffb_token' => 'encrypted'])
        ->and($none)->toBe([]);
});
