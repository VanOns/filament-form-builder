<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;

class FileUploadField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.file-upload-field';

    public ?string $placeholder = null;
    public ?bool $multiple = false;

    public function getOriginalKey(): string
    {
        return parent::getKey();
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

    protected function rules(): array
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

        return array_filter([
            $key => !empty($rules = $this->rules())
                ? $rules
                : null,
            $key . '.*' => $this->multiple ? [
                'file',
                'max:' . $this->getMaxSize(),
            ] : null,
        ]);
    }

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
            TextInput::make('placeholder'),
            Checkbox::make('multiple')
                ->columnSpanFull()
                ->label(__('filament-form-builder::fields.multiple_uploads'))
                ->default(false),
        ];
    }
}
