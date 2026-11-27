<?php

use VanOns\FilamentFormBuilder\Classes\SubmissionMeta;

it('reads a user agent as a browser and a system', function (string $userAgent, string $reads) {
    expect(SubmissionMeta::describeUserAgent($userAgent))->toBe($reads);
})->with([
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'Chrome · macOS'],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Safari · iOS'],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 'Edge · Windows'],
    ['Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', 'Firefox · Linux'],
    ['curl/8.7.1', 'curl/8.7.1'],
]);

it('leaves out the part of an address that points at one connection', function (?string $ip, ?string $anonymized) {
    expect(SubmissionMeta::anonymizeIp($ip))->toBe($anonymized);
})->with([
    ['203.0.113.42', '203.0.113.0'],
    ['2001:db8:85a3:8d3:1319:8a2e:370:7348', '2001:db8:85a3::'],
    ['not an address', null],
    [null, null],
]);

it('takes the campaign from the utm parameters only', function () {
    expect(SubmissionMeta::campaign(['utm_source' => 'nieuwsbrief', 'utm_campaign' => 'najaar', 'vacature' => 'adviseur', 'utm_term' => '']))
        ->toBe(['source' => 'nieuwsbrief', 'campaign' => 'najaar']);
});

it('reads each kind of detail the way the page shows it', function () {
    $meta = [
        'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0',
        'user' => ['id' => 7, 'name' => 'Jesse'],
        'campaign' => ['source' => 'nieuwsbrief', 'medium' => 'email'],
        'ip' => '203.0.113.0',
    ];

    expect(SubmissionMeta::describe($meta, 'user_agent'))->toBe('Firefox · Linux')
        ->and(SubmissionMeta::describe($meta, 'user'))->toBe('Jesse (#7)')
        ->and(SubmissionMeta::describe($meta, 'campaign'))->toBe('source: nieuwsbrief, medium: email')
        ->and(SubmissionMeta::describe($meta, 'ip'))->toBe('203.0.113.0')
        ->and(SubmissionMeta::describe($meta, 'locale'))->toBeNull()
        ->and(SubmissionMeta::describe(null, 'ip'))->toBeNull();
});

it('names only what the config collects', function () {
    config(['filament-form-builder.submission_meta' => ['user_agent' => true, 'locale' => false, 'ip' => 'full']]);

    expect(array_keys(SubmissionMeta::labels()))->toBe(['user_agent', 'ip']);
});
