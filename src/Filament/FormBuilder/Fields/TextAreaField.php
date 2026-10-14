<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;

class TextAreaField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.text-area-field';
    public static string $previewView = 'filament-form-builder::filament.previews.text-area';

    public ?string $placeholder = null;
    public ?int $rows = null;

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function rows(?int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    protected function fieldRules(): array
    {
        $max = config('filament-form-builder.max_length.textarea', 20000);

        return [
            ...$this->getDefaultRules(),
            'string',
            ...(is_numeric($max) ? ['max:' . (int) $max] : []),
        ];
    }

    public static function minWidth(): FieldWidth
    {
        return FieldWidth::THIRD;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedBars3BottomLeft;
    }

    public static function editableSettings(): array
    {
        return [...parent::editableSettings(), 'placeholder'];
    }

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
            TextInput::make('placeholder'),
            TextInput::make('rows')
                ->label(__('filament-form-builder::fields.rows'))
                ->numeric(),
        ];
    }
}
