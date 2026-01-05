<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;

trait HasFields
{
    public ?bool $large = false;
    public ?string $description;

    /**
     * @return array<Component>
     */
    abstract public static function getFields(): array;

    /**
     * @return array<Component>
     */
    protected static function getDefaultFields(): array
    {
        $getKey = function (Get $get, ?string $state) {
            $key = \Str::snake($get('key') ?? $state ?? '');
            $prefix = FormField::$keyPrefix;
            return empty($key)
                ? null
                : __('filament-form-builder::fields.key') . ": {$prefix}{$key}";
        };

        return [
            TextInput::make('label')
                ->reactive()
                ->helperText($getKey(...))
                ->label(__('filament-form-builder::fields.label')),
            TextInput::make('key')
                ->required()
                ->reactive()
                ->visible(fn (Get $get) => $get('set_key'))
                ->label(__('filament-form-builder::fields.key')),
            Group::make([
                Checkbox::make('required')
                    ->default(false)
                    ->label(__('filament-form-builder::fields.required')),
                Checkbox::make('large')
                    ->default(false)
                    ->label(__('filament-form-builder::fields.large')),
                Checkbox::make('set_key')
                    ->default(false)
                    ->label(__('filament-form-builder::fields.set_key'))
                    ->afterStateUpdated(fn (Set $set) => $set('key', ''))
                    ->reactive(),
            ])->columnStart(1)
                ->columnSpanFull()
                ->columns(4),
            TextInput::make('description')
                ->columnStart(1)
                ->label(__('filament-form-builder::fields.description')),
        ];
    }
}
