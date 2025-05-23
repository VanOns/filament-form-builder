<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;

class FieldSelect extends Select
{
    protected function setUp(): void
    {
        $fields = collect(config('filament-form-builder.fields', []))
            ->mapWithKeys(fn ($field) => [$field => $field::label()])
            ->toArray();

        $this->options($fields)
            ->placeholder(__('filament-form-builder::fields.form_builder_placeholder'))
            ->reactive()
            ->afterStateUpdated(fn (Get $get, Set $set) => $set('./', ['fieldType' => $get('fieldType')]));
    }
}
