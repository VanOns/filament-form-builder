<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;

class FileUploadField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.file-upload-field';

    public ?string $placeholder = null;
    public bool $multiple = false;

    public function getKey(): string
    {
        $key = parent::getKey();
        if ($this->multiple) {
            $key .= '[]';
        }

        return $key;
    }

    protected function rules(): array
    {
        $rules = [];
        if ($this->multiple) {
            $rules[] = 'array';
        } else {
            $rules[] = 'file';
        }

        return [
            ...$this->getDefaultRules(),
            ...$rules,
        ];
    }

    public function getRules(): array
    {
        $key = str_replace('[]', '', $this->getKey());

        return array_filter([
            $key => !empty($rules = $this->rules())
                ? $rules
                : null,
            $key . '.*' => $this->multiple ? 'file' : null,
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
