<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\RichEditor;
use Illuminate\Support\Str;

class TextField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.text-field';
    public static string $itemLabelField = 'text';

    public ?string $text;

    public static function getFields(): array
    {
        return [
            RichEditor::make('text')
                ->required()
                ->label(__('filament-form-builder::general.text'))
                ->columnStart(1),
        ];
    }

    protected function rules(): array
    {
        return $this->getDefaultRules();
    }

    /**
     * @param array<string, mixed> $item
     * @return string|null
     */
    public static function getItemLabel(array $item): ?string
    {
        $text = $item['text'] ?? null;
        return $text
            ? Str::limit(strip_tags($text), 50)
            : null;
    }

    public static function isInput(): bool
    {
        return false;
    }

    public static function hasVisibilitySettings(): bool
    {
        return false;
    }
}
