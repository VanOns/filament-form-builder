<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

abstract class Field
{
    public static string $view;
    public string $key;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
            if (!isset($this->key)) {
                $this->key = Str::snake($key);
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
            TextInput::make('label'),
            Toggle::make('required')
                ->default(false),
        ];
    }

    /**
     * @return array<Component>
     */
    public function make(): array
    {
        return static::getFields();
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
