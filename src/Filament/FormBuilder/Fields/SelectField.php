<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Arr;

class SelectField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.select-field';

    /**
     * @var array<string, string>
     */
    public array $options = [];
    public ?bool $multiple = false;
    public ?string $placeholder;

    protected function rules(): array
    {
        $values = implode(
            ',',
            Arr::pluck($this->options, 'value'),
        );

        return [
            ...$this->getDefaultRules(),
            "exists:{$values}",
        ];
    }

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
            TextInput::make('placeholder'),
            Checkbox::make('multiple')
                ->columnSpanFull()
                ->label(__('filament-form-builder::fields.multiple_choice_question'))
                ->default(false),
            Repeater::make('options')
                ->itemLabel(function (?array $state) {
                    $join = array_filter([
                        $state['label'] ?? null,
                        $state['value'] ?? null,
                    ]);

                    return !empty($join)
                        ? implode(' - ', $join)
                        : '-';
                })
                ->collapsed()
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
