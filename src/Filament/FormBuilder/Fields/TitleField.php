<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;

class TitleField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.title-field';
    public static string $previewView = 'filament-form-builder::filament.previews.title';

    public ?string $title = null;
    public ?string $headingLevel = 'h2';

    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function headingLevel(string $level): static
    {
        $this->headingLevel = $level;

        return $this;
    }

    public static function startsGroup(): bool
    {
        return true;
    }

    public function getGroupTitle(): ?string
    {
        return $this->title;
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
        return Heroicon::OutlinedH1;
    }

    public static function editableSettings(): array
    {
        return ['title'];
    }

    public static function getFields(): array
    {
        return [
            TextInput::make('title')
                ->label(__('filament-form-builder::general.title')),
            Select::make('headingLevel')
                ->label(__('filament-form-builder::general.heading_level'))
                ->default('h2')
                ->options([
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6',
                ]),
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

    public static function canHaveConditions(): bool
    {
        return true;
    }
}
