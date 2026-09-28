<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SelectField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.select-field';

    /**
     * @var array<int, array{value?: string, label?: string}>
     */
    public array $options = [];
    public ?bool $multiple = false;
    public ?string $placeholder;

    /**
     * @return array<string, string>
     */
    public function getFilterOptions(): array
    {
        $options = [];

        foreach ($this->options as $option) {
            $value = $option['value'] ?? null;

            if ($value !== null && $value !== '') {
                $options[$value] = $option['label'] ?? $value;
            }
        }

        return $options;
    }

    public function formatSubmissionValue(mixed $value): mixed
    {
        $options = $this->getFilterOptions();

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $options[$item] ?? $item, $value);
        }

        return is_string($value) ? ($options[$value] ?? $value) : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        $values = Arr::pluck($this->options, 'value');

        return [
            $this->getKey() => array_filter([
                ...$this->getDefaultRules(),
                !$this->multiple
                    ? Rule::in($values)
                    : null,
            ]),
            $this->getKey() . '.*' => array_filter([
                $this->multiple
                    ? Rule::in($values)
                    : null,
            ]),
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
                ->grid()
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
                ->afterStateHydrated(static function (Component $component, ?array $rawState): void {
                    $component->rawState(
                        collect($rawState ?? [])
                            ->mapWithKeys(fn ($itemData) => [(string) Str::uuid() => $itemData])
                            ->toArray(),
                    );
                })
                ->schema([
                    TextInput::make('value')
                        ->required(),
                    TextInput::make('label')
                        ->required(),
                ]),
        ];
    }
}
