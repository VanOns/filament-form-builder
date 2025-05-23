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

abstract class FormField
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
                Checkbox::make('required')
                    ->default(false)
                    ->label(__('filament-form-builder::fields.required')),
                Checkbox::make('set_key')
                    ->default(false)
                    ->label(__('filament-form-builder::fields.set_key'))
                    ->afterStateUpdated(fn (Set $set) => $set('key', ''))
                    ->reactive(),
            ])->columnStart(1)->columns(4),
            TextInput::make('description')
                ->columnStart(1)
                ->label(__('filament-form-builder::fields.description')),
        ];
    }

    /**
     * @return array<mixed>
     */
    protected function getDefaultRules(): array
    {
        return array_filter([
            $this->required
                ? 'required'
                : null,
        ]);
    }

    /**
     * @return array<Component>
     */
    public function make(): array
    {
        return static::getFields();
    }

    protected function generateLabel(): string
    {
        $data = array_filter([
            static::label(),
            $this->label ?? null,
        ]);

        return Str::snake(implode(' ', $data));
    }

    public function getKey(): string
    {
        return !isset($this->key)
            ? $this->key = $this->generateLabel()
            : $this->key;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return $this->getDefaultRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        return [
            $this->getKey() => !empty($rules = $this->rules())
                ? $rules
                : null,
        ];
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
