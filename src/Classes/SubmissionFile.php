<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Illuminate\Support\Facades\URL;
use Stringable;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class SubmissionFile implements Stringable
{
    public function __construct(
        public readonly FormSubmission $submission,
        public readonly string $key,
        public readonly int $index,
        public readonly string $path,
        public readonly string $name,
    ) {
    }

    /**
     * Whoever holds the link may download the file until it expires, so it can
     * travel in a notification mail to someone without an account.
     */
    public function url(): string
    {
        return URL::temporarySignedRoute(
            'filament-form-builder.form.download-file',
            now()->addDays((int) config('filament-form-builder.form-uploads-link-days', 7)),
            ['submissionId' => $this->submission->getKey(), 'key' => $this->key, 'index' => $this->index],
        );
    }

    public function __toString(): string
    {
        return $this->url();
    }
}
