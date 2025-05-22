<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Forms\Components\Select;

class FieldSelect extends Select
{
    protected function setUp(): void
    {
        $fields = collect(config('filament-form-builder.fields', []))
            ->mapWithKeys(fn ($field) => [$field => $field::label()])
            ->toArray();

        $this->options($fields)
            ->placeholder(__('filament-form-builder::fields.form_builder_placeholder'));
    }
}
