<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;

class FieldSelect extends Select
{
    protected function setUp(): void
    {
        /** @var array<int, class-string<FormField>> $configFields */
        $configFields = (array) config('filament-form-builder.fields', []);

        $fields = collect($configFields)
            ->mapWithKeys(fn (string $field): array => [$field => $field::label()])
            ->toArray();

        $this->options($fields)
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
