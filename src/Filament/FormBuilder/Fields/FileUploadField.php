<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;

class FileUploadField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.file-upload-field';
    public static string $previewView = 'filament-form-builder::filament.previews.file-upload';

    public ?bool $multiple = false;

    public function multiple(bool $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
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

    protected function getMaxSize(): ?int
    {
        $maxSize = config('filament-form-builder.uploads.max_size');

        return is_int($maxSize) ? $maxSize : null;
    }

    /**
     * The extensions an upload may have, judged by its content; null takes any.
     *
     * @return list<string>|null
     */
    public static function getAllowedExtensions(): ?array
    {
        $extensions = config('filament-form-builder.uploads.extensions');

        return is_array($extensions) ? array_values(array_filter(array_map(fn (mixed $extension): string => strtolower(ltrim((string) $extension, '.')), $extensions))) : null;
    }

    /**
     * What the file input offers to pick, the same list the server checks.
     */
    public function getAccept(): ?string
    {
        $extensions = static::getAllowedExtensions();

        return $extensions === null ? null : implode(',', array_map(fn (string $extension): string => ".{$extension}", $extensions));
    }

    /**
     * @return list<string>
     */
    protected function fileRules(): array
    {
        $maxSize = $this->getMaxSize();
        $extensions = static::getAllowedExtensions();

        return [
            'file',
            ...($maxSize !== null ? ['max:' . $maxSize] : []),
            ...($extensions !== null ? ['mimes:' . implode(',', $extensions)] : []),
        ];
    }

    protected function fieldRules(): array
    {
        return [
            ...$this->getDefaultRules(),
            ...($this->multiple ? ['array'] : $this->fileRules()),
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
                ->default(false),
        ];
    }
}
