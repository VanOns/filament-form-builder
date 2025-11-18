<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class TitleField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.title-field';
    public static string $itemLabelField = 'title';

    public ?string $title;
    public ?string $headingLevel = 'h2';

    public static function getFields(): array
    {
        return [
            TextInput::make('title'),
            Select::make('headingLevel')
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

    protected function rules(): array
    {
        return $this->getDefaultRules();
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
