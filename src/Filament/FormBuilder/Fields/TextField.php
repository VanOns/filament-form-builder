<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;

class TextField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.text-field';
    public static string $previewView = 'filament-form-builder::filament.previews.text';

    public ?string $text = null;

    public function text(?string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public static function paletteGroup(): string
    {
        return 'layout';
    }

    public static function minWidth(): FieldWidth
    {
        return FieldWidth::THIRD;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedDocumentText;
    }

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

    protected function fieldRules(): array
    {
        return [];
    }

    public static function isInput(): bool
    {
        return false;
    }

    public static function hasConditionSettings(): bool
    {
        return false;
    }
}
