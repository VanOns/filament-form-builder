<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
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
        return $this->signedUrl([]);
    }

    public function downloadUrl(): string
    {
        return $this->signedUrl(['download' => 1]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function signedUrl(array $query): string
    {
        return URL::temporarySignedRoute(
            'filament-form-builder.form.download-file',
            now()->addDays((int) config('filament-form-builder.form-uploads-link-days', 7)),
            ['submissionId' => $this->submission->getKey(), 'key' => $this->key, 'index' => $this->index, ...$query],
        );
    }

    public function exists(): bool
    {
        return $this->disk()->exists($this->path);
    }

    public function size(): ?int
    {
        return $this->exists() ? $this->disk()->size($this->path) : null;
    }

    public function mimeType(): ?string
    {
        $disk = $this->disk();

        return $disk instanceof FilesystemAdapter && $this->exists() ? ($disk->mimeType($this->path) ?: null) : null;
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    public function opensInBrowser(): bool
    {
        return static::isSafeInline($this->mimeType());
    }

    /**
     * Only types that cannot run script on this domain open in the browser.
     */
    public static function isSafeInline(?string $mimeType): bool
    {
        return $mimeType === 'application/pdf'
            || ($mimeType !== null && str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml');
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(FormSubmission::getFilesDisk());
    }

    public function __toString(): string
    {
        return $this->url();
    }
}
