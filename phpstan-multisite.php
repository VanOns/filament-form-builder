<?php

// filament-multisite is private, so CI analyses without it; `composer multisite` brings it in.
if (is_dir(__DIR__ . '/vendor/van-ons/filament-multisite')) {
    return [];
}

return [
    'parameters' => [
        'excludePaths' => [
            'analyse' => [__DIR__ . '/src/Models/SiteAwareForm.php'],
        ],
        'ignoreErrors' => [
            ['identifier' => 'class.notFound', 'message' => '#VanOns\\\\FilamentMultisite\\\\#'],
        ],
    ],
];
