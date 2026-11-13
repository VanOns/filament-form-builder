<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use VanOns\FilamentFormBuilder\Rules\TextLength;

/**
 * A minimum and a maximum number of characters for a text answer, never past
 * what the config allows for the field type.
 */
trait HasLength
{
    public int|string|null $minLength = null;

    public int|string|null $maxLength = null;

    public function minLength(?int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    public function maxLength(?int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    /**
     * The `max_length` config of the field type: `text` or `textarea`.
     */
    abstract protected static function lengthLimitKey(): string;

    /**
     * The most characters the config allows; null for no limit.
     */
    public static function getLengthLimit(): ?int
    {
        $key = static::lengthLimitKey();
        // The defaults of the config, for a project whose own copy leaves the key out.
        $limit = config("filament-form-builder.max_length.{$key}", ['text' => 1000, 'textarea' => 20000][$key] ?? null);

        return is_numeric($limit) ? (int) $limit : null;
    }

    public function getMinLength(): ?int
    {
        return is_numeric($this->minLength) && (int) $this->minLength > 0 ? (int) $this->minLength : null;
    }

    public function getMaxLength(): ?int
    {
        $limit = static::getLengthLimit();
        $max = is_numeric($this->maxLength) && (int) $this->maxLength > 0 ? (int) $this->maxLength : null;

        return $max === null || $limit === null ? $max : min($max, $limit);
    }

    /**
     * @return array<int, mixed>
     */
    protected function getLengthRules(): array
    {
        $limit = static::getLengthLimit();
        $min = $this->getMinLength();
        $max = $this->getMaxLength();

        return [
            // Without these a visitor could post an array or a whole file under the key.
            'string',
            ...($limit === null ? [] : ["max:{$limit}"]),
            ...($min === null && $max === null ? [] : [new TextLength($min, $max)]),
        ];
    }

    public function getAttributesList(bool $withRequired = true): array
    {
        $min = $this->getMinLength();
        $max = $this->getMaxLength();

        return [
            ...parent::getAttributesList($withRequired),
            ...array_filter([
                'minlength' => $min === null ? null : (string) $min,
                'maxlength' => $max === null ? null : (string) $max,
            ], fn (?string $value): bool => $value !== null),
        ];
    }

    public static function getValidationFields(): array
    {
        $limit = static::getLengthLimit();

        return [
            TextInput::make('minLength')
                ->label(__('filament-form-builder::fields.min_length'))
                ->integer()
                ->minValue(1)
                ->maxValue($limit),

            TextInput::make('maxLength')
                ->label(__('filament-form-builder::fields.max_length'))
                ->integer()
                ->minValue(fn (Get $get): int => is_numeric($get('minLength')) ? max(1, (int) $get('minLength')) : 1)
                ->maxValue($limit),
        ];
    }
}
