<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class FieldSelect extends Select
{
    protected function setUp(): void
    {
        /** @var array<int, class-string<Fields\FormField>> $configured */
        $configured = (array) config('filament-form-builder.fields', []);

        $fields = collect($configured)
            ->mapWithKeys(fn (string $field): array => [$field => $field::label()])
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
