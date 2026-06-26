<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class FieldSelect extends Select
{
    protected function setUp(): void
    {
        $fields = collect((array) config('filament-form-builder.fields', []))
            ->mapWithKeys(
                /** @param class-string<FormField> $field */
                fn (string $field): array => [$field => $field::label()]
            )
            ->toArray();

        $this->options($fields)
            ->label(__('filament-form-builder::fields.field_type'))
            ->placeholder(__('filament-form-builder::fields.form_builder_placeholder'))
            ->reactive()
            ->helperText(function (?string $state) {
                if (!$state || !class_exists($state) || !method_exists($state, 'getHelperText')) {
                    return null;
                }

                return $state::getHelperText();
            })
            ->afterStateUpdated(fn (Get $get, Set $set) => $set('./', ['fieldType' => $get('fieldType')]));
    }
}
