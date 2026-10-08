<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;

/**
 * Starts a step of a form, with the step's title. The fields before the first
 * one form the first step, titled on the canvas's start block.
 */
class StepField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.step-field';
    public static string $previewView = 'filament-form-builder::filament.previews.step';

    public ?string $title = null;

    public function title(?string $title): static
    {
        $this->title = $title;

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
        return FieldWidth::FULL;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedForward;
    }

    public static function canBeHidden(): bool
    {
        return false;
    }

    public static function getFields(): array
    {
        return [
            TextInput::make('title')
                ->label(__('filament-form-builder::general.steps.title'))
                ->columnSpanFull(),
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
}
