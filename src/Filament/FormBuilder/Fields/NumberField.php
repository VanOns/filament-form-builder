<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use VanOns\FilamentFormBuilder\Enums\ConditionOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;
use VanOns\FilamentFormBuilder\Rules\MaxDecimals;

/**
 * A number, whole unless it is given decimals, and within a minimum and a
 * maximum when it has them. The browser gets the same limits as the server.
 */
class NumberField extends InputField
{
    /**
     * The most decimals an editor can pick; code may set more.
     */
    public const MAX_DECIMALS = 3;

    public int|float|string|null $min = null;

    public int|float|string|null $max = null;

    public int|string|null $decimals = null;

    public function min(int|float|null $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int|float|null $max): static
    {
        $this->max = $max;

        return $this;
    }

    /**
     * How many decimals an answer may have; none makes it a whole number.
     */
    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function getMin(): int|float|null
    {
        return is_numeric($this->min) ? $this->min + 0 : null;
    }

    public function getMax(): int|float|null
    {
        return is_numeric($this->max) ? $this->max + 0 : null;
    }

    public function getDecimals(): int
    {
        return max(0, (int) $this->decimals);
    }

    /**
     * The step of the number input; null for whole numbers, the browser's own step.
     */
    public function getStep(): ?string
    {
        $decimals = $this->getDecimals();

        return $decimals === 0 ? null : '0.' . str_repeat('0', $decimals - 1) . '1';
    }

    public function getInputType(): string
    {
        return 'number';
    }

    public function getAttributesList(bool $withRequired = true): array
    {
        $min = $this->getMin();
        $max = $this->getMax();

        return [
            ...parent::getAttributesList($withRequired),
            ...array_filter([
                'min' => $min === null ? null : (string) $min,
                'max' => $max === null ? null : (string) $max,
                'step' => $this->getStep(),
            ], fn (?string $value): bool => $value !== null),
        ];
    }

    public function getConditionOperators(): array
    {
        return ConditionOperator::options(ConditionOperator::numeric());
    }

    public function getConditionValueComponent(): Field
    {
        return TextInput::make('value')->numeric();
    }

    public function getFilterConstraints(): array
    {
        return [AnswerConstraints::number($this->getKey(), $this->getColumnLabel())];
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedHashtag;
    }

    // A number sent as JSON is no string, and `numeric` already says what it may be.
    protected function getLengthRules(): array
    {
        return [];
    }

    protected function getTypeRules(): array
    {
        $min = $this->getMin();
        $max = $this->getMax();
        $decimals = $this->getDecimals();

        return [
            'numeric',
            $decimals === 0 ? 'integer' : new MaxDecimals($decimals),
            ...($min === null ? [] : ["min:{$min}"]),
            ...($max === null ? [] : ["max:{$max}"]),
        ];
    }

    public static function getValidationFields(): array
    {
        return [
            TextInput::make('min')
                ->label(__('filament-form-builder::fields.minimum'))
                ->numeric(),

            TextInput::make('max')
                ->label(__('filament-form-builder::fields.maximum'))
                ->numeric()
                ->minValue(fn (Get $get): mixed => is_numeric($get('min')) ? $get('min') : null),

            Select::make('decimals')
                ->label(__('filament-form-builder::fields.decimals'))
                ->options(fn (): array => array_map(
                    fn (int $decimals): string => $decimals === 0
                        ? __('filament-form-builder::fields.decimals_none')
                        : trans_choice('filament-form-builder::fields.decimals_count', $decimals, ['count' => $decimals]),
                    range(0, self::MAX_DECIMALS),
                ))
                ->default(0)
                ->selectablePlaceholder(false),
        ];
    }
}
