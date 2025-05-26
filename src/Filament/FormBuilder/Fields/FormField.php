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
    public ?bool $large = false;
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

    public function getLabel(): string
    {
        return $this->label ?? static::label();
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
     * @return string|null
     */
    public static function getHelperText(): ?string
    {
        return null;
    }

    /**
     * @return array<Component>
     */
    public function make(): array
    {
        return static::getFields();
    }

    protected function generateKey(): string
    {
        return Str::snake(
            $this->getLabel()
        );
    }

    public function getKey(): string
    {
        return !isset($this->key)
            ? $this->key = $this->generateKey()
            : $this->key;
    }

    /**
     * @return array<int|string, mixed>
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
