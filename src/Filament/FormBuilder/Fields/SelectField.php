<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class SelectField extends Field
{
    public static string $view = 'filament-form-builder::components.fields.select-field';

    /**
     * @var array<string, string>
     */
    public array $options = [];
    public bool $multiple = false;

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
            Checkbox::make('multiple')
                ->columnSpanFull()
                ->label(__('filament-form-builder::fields.multiple_choice_question'))
                ->default(false),
            Repeater::make('options')
                ->columnSpanFull()
                ->label(__('filament-form-builder::fields.options'))
                ->columns()
                ->schema([
                    TextInput::make('value')
                        ->required(),
                    TextInput::make('label')
                        ->required(),
                ]),
        ];
    }
}
