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

    protected ?bool $exists = null;

    protected ?int $size = null;

    protected ?string $mimeType = null;

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
            now()->addDays(static::linkDays()),
            ['submissionId' => $this->submission->getKey(), 'key' => $this->key, 'index' => $this->index, ...$query],
        );
    }

    public static function linkDays(): int
    {
        return (int) config('filament-form-builder.uploads.link_days', 7);
    }

    public function exists(): bool
    {
        return $this->exists ??= $this->disk()->exists($this->path);
    }

    public function size(): ?int
    {
        return $this->exists() ? ($this->size ??= $this->disk()->size($this->path)) : null;
    }

    public function mimeType(): ?string
    {
        $disk = $this->disk();

        if (!$disk instanceof FilesystemAdapter || !$this->exists()) {
            return null;
        }

        return $this->mimeType ??= ($disk->mimeType($this->path) ?: null);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mimeType(), 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mimeType() === 'application/pdf';
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    /**
     * Only types that cannot run script on this domain open in the browser.
     */
    public function opensInBrowser(): bool
    {
        return $this->isPdf() || ($this->isImage() && $this->mimeType() !== 'image/svg+xml');
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
