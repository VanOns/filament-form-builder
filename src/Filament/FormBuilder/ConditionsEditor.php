<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use VanOns\FilamentFormBuilder\Enums\ConditionOperator;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Models\Form;

/**
 * Rules on the answers of a form, stored as `conditions` and `conditionMatch`:
 * when a field shows on the canvas, or when a notification goes out.
 */
class ConditionsEditor
{
    /**
     * @param  array<string, FormField>  $fields  the fields the rules can look at, by key
     * @param  string  $summary  the sentence the rules go into, with `:rules`
     * @param  string  $matchLabel  the label above all or one of the rules
     */
    public function __construct(
        private readonly array $fields,
        private readonly string $summary = 'filament-form-builder::general.canvas.conditions.summary',
        private readonly string $matchLabel = 'filament-form-builder::fields.condition_match',
        private readonly bool $isRequired = false,
    ) {
    }

    /**
     * The fields of a form a rule can look at, by key.
     *
     * @return array<string, FormField>
     */
    public static function fieldsOf(Form $form): array
    {
        $fields = [];

        foreach ($form->getFields(inputsOnly: true) as $field) {
            $fields[$field->getKey()] = $field;
        }

        return $fields;
    }

    /**
     * @return array<Component>
     */
    public function schema(): array
    {
        $needsValue = fn (Get $get): bool => ConditionOperator::tryFrom((string) $get('operator'))?->needsValue() ?? false;
        $operators = fn (Get $get): array => $this->operators($this->fields[$get('key')] ?? null);

        return [
            ToggleButtons::make('conditionMatch')
                ->label(__($this->matchLabel))
                ->options([
                    'all' => __('filament-form-builder::fields.condition_match_all'),
                    'any' => __('filament-form-builder::fields.condition_match_any'),
                ])
                ->default('all')
                ->formatStateUsing(fn (?string $state): string => $state ?? 'all')
                ->grouped()
                ->visible(fn (Get $get): bool => count($get('conditions') ?? []) > 1),
            Repeater::make('conditions')
                ->label(__('filament-form-builder::fields.conditions'))
                ->hiddenLabel()
                ->default([])
                ->table([
                    TableColumn::make(__('filament-form-builder::fields.condition_field'))->width('40%'),
                    TableColumn::make(__('filament-form-builder::fields.condition_operator'))->width('25%'),
                    TableColumn::make(__('filament-form-builder::fields.value')),
                ])
                ->reorderable(false)
                ->live()
                ->required($this->isRequired)
                ->minItems($this->isRequired ? 1 : null)
                ->addActionLabel(__('filament-form-builder::fields.add_condition'))
                ->schema([
                    Select::make('key')
                        ->label(__('filament-form-builder::fields.condition_field'))
                        ->options(array_map(fn (FormField $field): string => $field->getLabel(), $this->fields))
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set) use ($operators): void {
                            if (!array_key_exists((string) $get('operator'), $operators($get))) {
                                $set('operator', array_key_first($operators($get)));
                            }

                            $set('value', null);
                        }),
                    Select::make('operator')
                        ->label(__('filament-form-builder::fields.condition_operator'))
                        ->options($operators)
                        ->default(ConditionOperator::EQUALS->value)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    // One cell for the value, in the input the chosen field asks for.
                    Group::make(fn (Get $get): array => [
                        (($this->fields[$get('key')] ?? null)?->getConditionValueComponent() ?? TextInput::make('value'))
                            ->label(__('filament-form-builder::fields.value'))
                            ->required($needsValue)
                            ->visible($needsValue),
                    ]),
                ]),
            Text::make(fn (Get $get): ?string => $this->describe($get('conditions') ?? [], $get('conditionMatch')))
                ->visible(fn (Get $get): bool => filled($get('conditions'))),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function operators(?FormField $field): array
    {
        return $field?->getConditionOperators() ?? ConditionOperator::options(ConditionOperator::basic());
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
            $operator = is_array($rule) ? ConditionOperator::tryFrom((string) ($rule['operator'] ?? '')) : null;

            if ($operator !== null && filled($rule['key'] ?? null) && (!$operator->needsValue() || filled($rule['value'] ?? null))) {
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
        $phrase = $field?->getConditionPhrase($operator) ?? $operator->value;

        return __("filament-form-builder::general.canvas.conditions.{$form}.{$phrase}", [
            'field' => $field?->getLabel() ?? (string) ($rule['key'] ?? ''),
            'value' => is_scalar($value) ? (string) ($field?->formatSubmissionValue($value) ?? $value) : '',
        ]);
    }
}
