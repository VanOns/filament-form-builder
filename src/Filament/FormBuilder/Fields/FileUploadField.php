<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;
use VanOns\FilamentFormBuilder\Rules\MaxFiles;

/**
 * An upload. A field can take fewer file types, smaller files and fewer files
 * than the config allows, never more.
 */
class FileUploadField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.file-upload-field';
    public static string $previewView = 'filament-form-builder::filament.previews.file-upload';

    public ?bool $multiple = false;

    public int|string|null $maxFiles = null;

    /**
     * @var array<int, string>|null
     */
    public ?array $extensions = null;

    public int|string|null $maxSize = null;

    public function multiple(bool $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    /**
     * The most files a field with multiple uploads takes.
     */
    public function maxFiles(?int $files): static
    {
        $this->maxFiles = $files;

        return $this;
    }

    /**
     * The file types this field takes, out of the ones the config allows.
     *
     * @param  array<int, string>|null  $extensions
     */
    public function extensions(?array $extensions): static
    {
        $this->extensions = $extensions;

        return $this;
    }

    /**
     * The largest a file may be, in kilobytes.
     */
    public function maxSize(?int $kilobytes): static
    {
        $this->maxSize = $kilobytes;

        return $this;
    }

    public function getMaxFiles(): ?int
    {
        $limit = static::getMaxFilesLimit();
        $max = is_numeric($this->maxFiles) && (int) $this->maxFiles > 0 ? (int) $this->maxFiles : null;

        return $max === null || $limit === null ? ($max ?? $limit) : min($max, $limit);
    }

    /**
     * The most files the config lets a field with multiple uploads take; null for no limit.
     */
    public static function getMaxFilesLimit(): ?int
    {
        $limit = config('filament-form-builder.uploads.max_files', 10);

        return is_numeric($limit) ? (int) $limit : null;
    }

    /**
     * The extensions an upload to this field may have; null takes any.
     *
     * @return list<string>|null
     */
    public function getExtensions(): ?array
    {
        $allowed = static::getAllowedExtensions();
        $own = static::normalizeExtensions($this->extensions ?? []);

        if ($own === []) {
            return $allowed;
        }

        // A type the config dropped since leaves the field with the ones it still allows.
        return $allowed === null ? $own : (array_values(array_intersect($own, $allowed)) ?: $allowed);
    }

    /**
     * @param  array<int|string, mixed>  $extensions
     * @return list<string>
     */
    protected static function normalizeExtensions(array $extensions): array
    {
        return array_values(array_filter(array_map(fn (mixed $extension): string => strtolower(ltrim((string) $extension, '.')), $extensions)));
    }

    public static function storesFiles(): bool
    {
        return true;
    }

    public function getSubmissionColumns(): array
    {
        return [$this->getKey() => $this->getColumnLabel()];
    }

    public function getFilterConstraints(): array
    {
        return [AnswerConstraints::file($this->getKey(), $this->getColumnLabel())];
    }

    public function getInputName(): string
    {
        return $this->getKey() . ($this->multiple ? '[]' : '');
    }

    /**
     * The largest a file may be, in kilobytes; null for no limit.
     */
    public function getMaxSize(): ?int
    {
        $limit = static::getMaxSizeLimit();
        $max = is_numeric($this->maxSize) && (int) $this->maxSize > 0 ? (int) $this->maxSize : null;

        return $max === null || $limit === null ? ($max ?? $limit) : min($max, $limit);
    }

    protected static function getMaxSizeLimit(): ?int
    {
        $limit = config('filament-form-builder.uploads.max_size');

        return is_int($limit) ? $limit : null;
    }

    /**
     * The extensions an upload may have, judged by its content; null takes any.
     *
     * @return list<string>|null
     */
    public static function getAllowedExtensions(): ?array
    {
        $extensions = config('filament-form-builder.uploads.extensions');

        return is_array($extensions) ? static::normalizeExtensions($extensions) : null;
    }

    /**
     * What the file input offers to pick, the same list the server checks.
     */
    public function getAccept(): ?string
    {
        $extensions = $this->getExtensions();

        return $extensions === null ? null : implode(',', array_map(fn (string $extension): string => ".{$extension}", $extensions));
    }

    /**
     * @return list<string>
     */
    protected function fileRules(): array
    {
        $maxSize = $this->getMaxSize();
        $extensions = $this->getExtensions();

        return [
            'file',
            ...($maxSize !== null ? ['max:' . $maxSize] : []),
            ...($extensions !== null ? ['mimes:' . implode(',', $extensions)] : []),
        ];
    }

    /**
     * @return list<MaxFiles>
     */
    protected function fileCountRules(): array
    {
        $maxFiles = $this->getMaxFiles();

        return $maxFiles === null ? [] : [new MaxFiles($maxFiles)];
    }

    protected function fieldRules(): array
    {
        return [
            ...$this->getDefaultRules(),
            ...($this->multiple ? ['array', ...$this->fileCountRules()] : $this->fileRules()),
        ];
    }

    public function getRules(): array
    {
        $key = $this->getKey();

        return array_filter($this->withExtraRules([
            $key => $this->fieldRules(),
            $key . '.*' => $this->multiple ? $this->fileRules() : null,
        ]));
    }

    public static function minWidth(): FieldWidth
    {
        return FieldWidth::THIRD;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedArrowUpTray;
    }

    public static function getDefaultValueComponent(): ?Component
    {
        return null;
    }

    public static function canBeHidden(): bool
    {
        return false;
    }

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
            Checkbox::make('multiple')
                ->columnSpanFull()
                ->label(__('filament-form-builder::fields.multiple_uploads'))
                ->default(false)
                ->live(),
        ];
    }

    public static function getValidationFields(): array
    {
        $allowed = static::getAllowedExtensions();
        $maxSize = static::getMaxSizeLimit();
        $maxFiles = static::getMaxFilesLimit();

        return array_values(array_filter([
            $allowed === null ? null : Select::make('extensions')
                ->label(__('filament-form-builder::fields.file_types'))
                ->placeholder(__('filament-form-builder::fields.file_types_all'))
                ->multiple()
                ->options(array_combine($allowed, $allowed))
                ->columnSpanFull(),

            TextInput::make('maxSize')
                ->label(__('filament-form-builder::fields.max_file_size'))
                ->integer()
                ->minValue(1)
                ->maxValue($maxSize)
                ->placeholder($maxSize === null ? null : (string) $maxSize)
                ->suffix('kB'),

            TextInput::make('maxFiles')
                ->label(__('filament-form-builder::fields.max_files'))
                ->integer()
                ->minValue(1)
                ->maxValue($maxFiles)
                ->placeholder($maxFiles === null ? null : (string) $maxFiles)
                ->visible(fn (Get $get): bool => (bool) $get('multiple')),
        ]));
    }
}
