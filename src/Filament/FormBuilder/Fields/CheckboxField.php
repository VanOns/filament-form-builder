<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;

class CheckboxField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.checkbox-field';
    public static string $previewView = 'filament-form-builder::filament.previews.checkbox';
    public static string $answerView = 'filament-form-builder::answers.boolean';

    /**
     * A ticked box is stored as "1"; people read yes or no.
     */
    public function formatSubmissionValue(mixed $value): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN)
            ? __('filament-form-builder::fields.yes')
            : __('filament-form-builder::fields.no');
    }

    public function getFilterConstraints(): array
    {
        return [AnswerConstraints::checkbox($this->getKey(), $this->getLabel())->icon(static::icon())];
    }

    public static function paletteGroup(): string
    {
        return 'choice';
    }

    public static function minWidth(): FieldWidth
    {
        return FieldWidth::THIRD;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedCheckCircle;
    }

    public static function getDefaultValueComponent(): ?Component
    {
        return Checkbox::make('defaultValue')
            ->label(__('filament-form-builder::fields.checked_by_default'));
    }

    public static function canBeHidden(): bool
    {
        return false;
    }

    /**
     * @return array<Component>
     */
    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
        ];
    }
}
