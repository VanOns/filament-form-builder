<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Group;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Set;
use Illuminate\Support\Str;

class TextField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.text-field';
    public static string $itemLabelField = 'text';

    public ?string $text = null;

    public static function getFields(): array
    {
        return [
            Group::make(function (?array $state, Set $set) {
                if (!array_key_exists('text', $state ?? [])) {
                    $set('text', '');
                }

                return [
                    RichEditor::make('text')
                        ->required()
                        ->label(__('filament-form-builder::general.text')),
                ];
            })->columnStart(1),
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
        $text = $item[static::$itemLabelField] ?? null;
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
