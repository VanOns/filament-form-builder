<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

abstract class ChoiceField extends FormField
{
    public static string $view = 'filament-form-builder::components.fields.choice-field';
    public static string $previewView = 'filament-form-builder::filament.previews.choice';

    /**
     * @var array<int|string, array{value?: string, label?: string}>
     */
    public array $options = [];

    /**
     * Either value => label pairs, or a list of `['value' => ..., 'label' => ...]`.
     *
     * @param  array<int|string, string|array{value: string, label?: string}>  $options
     */
    public function options(array $options): static
    {
        $this->options = [];

        foreach ($options as $value => $option) {
            $this->options[] = is_array($option)
                ? ['value' => $option['value'], 'label' => $option['label'] ?? $option['value']]
                : ['value' => (string) $value, 'label' => $option];
        }

        return $this;
    }

    abstract public static function allowsMultiple(): bool;

    /**
     * @return array<string, string>
     */
    public function getFilterOptions(): array
    {
        return static::toChoices($this->options);
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
        $allowed = Rule::in(Arr::pluck($this->options, 'value'));

        if (static::allowsMultiple()) {
            return $this->withExtraRules([
                $this->getKey() => [...$this->getDefaultRules(), 'array'],
                $this->getKey() . '.*' => [$allowed],
            ]);
        }

        return $this->withExtraRules([
            $this->getKey() => [...$this->getDefaultRules(), $allowed],
        ]);
    }

    public static function getDefaultValueComponent(): ?Component
    {
        return Select::make('defaultValue')
            ->label(__('filament-form-builder::fields.default_value'))
            ->options(fn (Get $get): array => static::toChoices((array) $get('options')))
            ->multiple(static::allowsMultiple());
    }

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
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

    /**
     * @param  array<mixed>  $options
     * @return array<string, string>
     */
    protected static function toChoices(array $options): array
    {
        $choices = [];

        foreach ($options as $option) {
            $value = is_array($option) ? ($option['value'] ?? null) : null;

            if (is_string($value) && $value !== '') {
                $choices[$value] = is_string($option['label'] ?? null) ? $option['label'] : $value;
            }
        }

        return $choices;
    }
}
