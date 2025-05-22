<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

abstract class Field
{
    public static string $view = '';
    public ?string $key;
    public ?string $label;
    public ?bool $required = false;
    public ?string $description;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public static function label(): string
    {
        $field = Str::replace('-', ' ', Str::kebab(class_basename(static::class)));
        return ucfirst($field);
    }

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
                ->label(__('filament-form-builder::fields.label')),
            TextInput::make('key')
                ->visible(fn (Get $get) => $get('set_key'))
                ->label(__('filament-form-builder::fields.key')),
            Group::make([
                Checkbox::make('set_key')
                    ->default(false)
                    ->label(__('filament-form-builder::fields.set_key'))
                    ->afterStateUpdated(fn (Set $set) => $set('key', ''))
                    ->reactive(),
                Checkbox::make('required')
                    ->default(false)
                    ->label(__('filament-form-builder::fields.required')),
            ])->columnStart(1)->columns(4),
            TextInput::make('description')
                ->columnSpanFull()
                ->label(__('filament-form-builder::fields.description')),
        ];
    }

    /**
     * @return array<Component>
     */
    public function make(): array
    {
        return static::getFields();
    }

    public function getKey(): string
    {
        return !isset($this->key)
            ? Str::snake(static::label()) . '_' . Str::random(8)
            : $this->key;
    }

    public function getView(): string
    {
        return static::$view;
    }

    /**
     * @return string
     */
    public function render(): string
    {
        return Blade::render(
            $this->getView(),
            ['field' => $this],
        );
    }
}
