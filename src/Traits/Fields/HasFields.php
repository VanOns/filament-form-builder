<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;

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
        return [
            TextInput::make('label')
                ->columnStart(1)
                ->label(__('filament-form-builder::fields.label')),
            TextInput::make('key')
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
            Section::make(__('filament-form-builder::fields.visibility'))
                ->columns(3)
                ->reactive()
                ->schema([
                    TextInput::make('visibleWhenKey')
                        ->label(__('filament-form-builder::fields.visible_when_key')),
                    Select::make('visibleWhenType')
                        ->label('Is')
                        ->options([
                            'equals' => __('filament-form-builder::fields.equals'),
                            'not_equals' => __('filament-form-builder::fields.not_equals'),
                            'empty' => __('filament-form-builder::fields.is_empty'),
                            'not_empty' => __('filament-form-builder::fields.is_not_empty'),
                        ])
                        ->default('equals')
                        ->required(fn (Get $get) => !empty($get('visibleWhenKey')))
                        ->hidden(fn (Get $get) => empty($get('visibleWhenKey')))
                        ->reactive(),
                    TextInput::make('visibleWhenValue')
                        ->visible(fn (Get $get) => !empty($get('visibleWhenKey')) && !empty($get('visibleWhenType') && !in_array($get('visibleWhenType'), ['empty', 'not_empty'])))
                        ->label(__('filament-form-builder::fields.value'))
                        ->required(fn (Get $get) => !empty($get('visibleWhenKey'))),
                ]),
        ];
    }
}
