<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use VanOns\FilamentFormBuilder\Enums\ConditionOperator;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;

/**
 * Rules on the answers of a form, stored as `conditions` and `conditionMatch`:
 * when a field shows on the canvas, or when a notification goes out.
 */
final class ConditionsEditor
{
    /**
     * @param  array<string, FormField>  $fields  the fields the rules can look at, by key
     * @param  string  $summary  the sentence the rules go into, with `:rules`
     */
    public function __construct(
        private readonly array $fields,
        private readonly string $summary = 'filament-form-builder::general.canvas.conditions.summary',
    ) {
    }

    /**
     * @return array<Component>
     */
    public function schema(): array
    {
        $needsValue = fn (Get $get): bool => ConditionOperator::tryFrom((string) $get('operator'))?->needsValue() ?? false;
        $choices = fn (Get $get): array => ($this->fields[$get('key')] ?? null)?->getFilterOptions() ?? [];

        return [
            ToggleButtons::make('conditionMatch')
                ->label(__('filament-form-builder::fields.condition_match'))
                ->options([
                    'all' => __('filament-form-builder::fields.condition_match_all'),
                    'any' => __('filament-form-builder::fields.condition_match_any'),
                ])
                ->default('all')
                ->formatStateUsing(fn (?string $state): string => $state ?? 'all')
                ->grouped()
                ->visible(fn (Get $get): bool => count($get('conditions') ?? []) > 1),
            Repeater::make('conditions')
                ->hiddenLabel()
                ->default([])
                ->columns(2)
                ->reorderable(false)
                ->live()
                ->addActionLabel(__('filament-form-builder::fields.add_condition'))
                ->schema([
                    Select::make('key')
                        ->label(__('filament-form-builder::fields.condition_field'))
                        ->options(array_map(fn (FormField $field): string => $field->getLabel(), $this->fields))
                        ->required()
                        ->live(),
                    Select::make('operator')
                        ->label(__('filament-form-builder::fields.condition_operator'))
                        ->options(ConditionOperator::options())
                        ->default(ConditionOperator::EQUALS->value)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    // A choice field offers its own options to pick from.
                    ToggleButtons::make('value')
                        ->label(__('filament-form-builder::fields.value'))
                        ->options($choices)
                        ->inline()
                        ->columnSpanFull()
                        ->required($needsValue)
                        ->visible(fn (Get $get): bool => $needsValue($get) && $choices($get) !== []),
                    TextInput::make('value')
                        ->label(__('filament-form-builder::fields.value'))
                        ->columnSpanFull()
                        ->required($needsValue)
                        ->visible(fn (Get $get): bool => $needsValue($get) && $choices($get) === []),
                ]),
            Text::make(fn (Get $get): ?string => $this->describe($get('conditions') ?? [], $get('conditionMatch')))
                ->visible(fn (Get $get): bool => filled($get('conditions'))),
        ];
    }

    /**
     * For a badge: the one rule, or how many there are and how they combine.
     *
     * @param  array<mixed>  $rules
     */
    public function badge(array $rules, ?string $match): ?string
    {
        $rules = array_values(array_filter($rules, fn (mixed $rule): bool => is_array($rule) && filled($rule['key'] ?? null)));

        if ($rules === []) {
            return null;
        }

        if (count($rules) > 1) {
            return __('filament-form-builder::general.canvas.conditions.badge_' . ($match === 'any' ? 'any' : 'all'), ['count' => count($rules)]);
        }

        return __('filament-form-builder::general.canvas.conditions.badge', [
            'rule' => $this->describeRule($rules[0], 'short'),
        ]);
    }

    /**
     * The rules as one sentence, for under the rules being edited.
     *
     * @param  array<mixed>  $rules
     */
    public function describe(array $rules, ?string $match): ?string
    {
        $described = [];

        foreach ($rules as $rule) {
            if (is_array($rule) && filled($rule['key'] ?? null) && filled($rule['operator'] ?? null)) {
                $described[] = $this->describeRule($rule, 'long');
            }
        }

        if ($described === []) {
            return null;
        }

        return __($this->summary, [
            'rules' => implode(__('filament-form-builder::general.canvas.conditions.join_' . ($match === 'any' ? 'any' : 'all')), $described),
        ]);
    }

    /**
     * @param  array<mixed>  $rule
     */
    private function describeRule(array $rule, string $form): string
    {
        $field = $this->fields[$rule['key'] ?? ''] ?? null;
        $value = $rule['value'] ?? null;
        $operator = ConditionOperator::tryFrom((string) ($rule['operator'] ?? '')) ?? ConditionOperator::EQUALS;

        return __("filament-form-builder::general.canvas.conditions.{$form}.{$operator->value}", [
            'field' => $field?->getLabel() ?? (string) ($rule['key'] ?? ''),
            'value' => is_scalar($value) ? ($field?->getFilterOptions()[(string) $value] ?? (string) $value) : '',
        ]);
    }
}
