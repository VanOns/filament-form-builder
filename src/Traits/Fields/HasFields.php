<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use VanOns\FilamentFormBuilder\Enums\FieldWidth;

trait HasFields
{
    public null|int|string $column_span = null;
    public ?string $description = null;

    public function span(int | FieldWidth $span): static
    {
        $this->column_span = $span instanceof FieldWidth ? $span->value : $span;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * The narrowest this field type still works at. The builder offers nothing
     * narrower, and a narrower stored width reads as this one.
     */
    public static function minWidth(): FieldWidth
    {
        return FieldWidth::QUARTER;
    }

    public function getWidth(): FieldWidth
    {
        return FieldWidth::fit($this->column_span, static::minWidth());
    }

    public function getColumnSpan(): int
    {
        return $this->getWidth()->value;
    }

    /**
     * @return array<Component>
     */
    abstract public static function getFields(): array;

    /**
     * The settings that limit what a visitor may answer, shown under
     * "Validation" in the field's slide-over. The field turns them into rules
     * in `fieldRules()`.
     *
     * @return array<Component>
     */
    public static function getValidationFields(): array
    {
        return [];
    }

    /**
     * @return array<Component>
     */
    protected static function getDefaultFields(): array
    {
        return [
            TextInput::make('label')
                ->live(onBlur: true)
                ->label(__('filament-form-builder::fields.label')),
            TextInput::make('description')
                ->label(__('filament-form-builder::fields.description')),
            Toggle::make('required')
                ->default(false)
                ->columnSpanFull()
                ->label(__('filament-form-builder::fields.required'))
                ->helperText(__('filament-form-builder::fields.required_helper')),
        ];
    }
}
