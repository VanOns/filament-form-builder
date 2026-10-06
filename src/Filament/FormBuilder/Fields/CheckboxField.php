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
     * What a ticked box can be stored as, so a filter can ask the database.
     *
     * @var list<string>
     */
    public const CHECKED_VALUES = ['1', 'true', 'on', 'yes'];

    public static function isChecked(mixed $value): bool
    {
        return in_array(mb_strtolower((string) (is_scalar($value) ? $value : '')), static::CHECKED_VALUES, true);
    }

    /**
     * A ticked box is stored as "1"; people read yes or no.
     */
    public function formatSubmissionValue(mixed $value): mixed
    {
        return static::isChecked($value)
            ? __('filament-form-builder::fields.yes')
            : __('filament-form-builder::fields.no');
    }

    public function getFilterConstraints(): array
    {
        return [AnswerConstraints::checkbox($this->getKey(), $this->getLabel())];
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
