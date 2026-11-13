<?php

namespace VanOns\FilamentFormBuilder\Classes;

use VanOns\FilamentFormBuilder\Enums\StepProgress;

/**
 * What a form in steps shows around them: the title of the first step, the
 * progress and the labels of the buttons between steps. Stored in
 * `custom.steps`, set on the canvas's start block.
 */
class StepSettings
{
    /**
     * @param  array<string, mixed>  $settings
     * @return array{title: ?string, progress: string, previous: ?string, next: ?string}
     */
    public static function normalize(array $settings): array
    {
        $text = fn (string $key): ?string => is_string($settings[$key] ?? null) && trim($settings[$key]) !== '' ? trim($settings[$key]) : null;
        $progress = $settings['progress'] ?? null;

        return [
            'title' => $text('title'),
            // The start block's radio hands over the enum itself.
            'progress' => ($progress instanceof StepProgress ? $progress : StepProgress::tryFrom(is_string($progress) ? $progress : '') ?? StepProgress::Steps)->value,
            'previous' => $text('previous'),
            'next' => $text('next'),
        ];
    }
}
