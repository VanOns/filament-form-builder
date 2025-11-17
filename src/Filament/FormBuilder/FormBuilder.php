<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Helpers\FieldHelper;

class FormBuilder extends Field
{
    protected string $view = 'filament-forms::components.group';

    public static function make(string $name): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        $this->schema([
            Repeater::make('fields')
                ->itemLabel(function (?array $state) {
                    $class = $state['fieldType'] ?? null;
                    if ($class && class_exists($class)) {
                        /** @var class-string<FormField> $class */
                        $itemLabel = $class::getItemLabel($state);
                        $label = $class::label();
                    }

                    $join = array_filter([
                        $itemLabel ?? null,
                        $label ?? null,
                    ]);

                    return !empty($join)
                        ? implode(' - ', $join)
                        : '-';
                })
                ->collapsed()
                ->hiddenLabel()
                ->schema([
                    Group::make(fn (Get $get) => [
                        FieldSelect::make('fieldType'),
                        ...FieldHelper::getFields($get('fieldType')),
                    ])->columns(),
                ]),
        ]);
    }
}
