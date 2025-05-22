<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
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
                ->hiddenLabel()
                ->schema([
                    FieldSelect::make('fieldType')
                        ->columnSpanFull(),

                    Group::make(
                        fn (Get $get) => FieldHelper::getFields($get('fieldType'))
                    )->columns(),
                ]),
        ]);
    }
}
