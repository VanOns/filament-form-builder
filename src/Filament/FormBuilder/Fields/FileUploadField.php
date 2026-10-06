<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

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

    public function getOriginalKey(): string
    {
        return parent::getKey();
    }

    public function getSubmissionColumns(): array
    {
        return [$this->getOriginalKey() => $this->getLabel()];
    }

    public function getKey(): string
    {
        $key = parent::getKey();
        if ($this->multiple) {
            $key .= '[]';
        }

        return $key;
    }

    protected function getMaxSize(): ?int
    {
        $maxSize = config('filament-form-builder.form-uploads-max-size');

        return is_int($maxSize) ? $maxSize : null;
    }

    protected function fieldRules(): array
    {
        $rules = [];
        if ($this->multiple) {
            $rules[] = 'array';
        } else {
            $rules[] = 'file';
            $rules[] = 'max:' . $this->getMaxSize();
        }

        return [
            ...$this->getDefaultRules(),
            ...$rules,
        ];
    }

    public function getRules(): array
    {
        $key = $this->getOriginalKey();

        return array_filter($this->withExtraRules([
            $key => $this->fieldRules(),
            $key . '.*' => $this->multiple ? [
                'file',
                'max:' . $this->getMaxSize(),
            ] : null,
        ]));
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
