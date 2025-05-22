<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

abstract class Field
{
    public static string $view;
    public string $key;
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
            TextInput::make('key')
                ->columnStart(1)
                ->visible(fn (Get $get) => $get('set_key'))
                ->label(__('filament-form-builder::fields.key')),
            TextInput::make('label')
                ->label(__('filament-form-builder::fields.label')),
            Checkbox::make('set_key')
                ->columnSpanFull()
                ->default(false)
                ->label(__('filament-form-builder::fields.set_key'))
                ->reactive(),
            Checkbox::make('required')
                ->columnSpanFull()
                ->default(false)
                ->label(__('filament-form-builder::fields.required')),
            TextInput::make('description')
                ->label(__('filament-form-builder::fields.description'))
                ->columnSpanFull()
                ->placeholder(__('filament-form-builder::fields.description_placeholder')),
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

    /**
     * @return string
     */
    public function render(): string
    {
        return Blade::render(
            static::$view,
            ['field' => $this],
        );
    }
}
