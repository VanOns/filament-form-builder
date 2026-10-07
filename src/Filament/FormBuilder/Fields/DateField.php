<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use VanOns\FilamentFormBuilder\Enums\ConditionOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;

/**
 * A date, stored the way a date input sends it (2026-10-07), so it sorts and
 * compares as text and reads as a date wherever it is shown.
 */
class DateField extends InputField
{
    /**
     * How an answer reads, the first by default.
     */
    public const FORMATS = ['j F Y', 'd-m-Y', 'j M Y', 'Y-m-d'];

    public static string $previewView = 'filament-form-builder::filament.previews.date';

    public ?string $format = null;

    public function getFormat(): string
    {
        return in_array($this->format, self::FORMATS, true) ? $this->format : self::FORMATS[0];
    }

    public function getInputType(): string
    {
        return 'date';
    }

    protected function getTypeRules(): array
    {
        return ['date_format:Y-m-d'];
    }

    public function formatSubmissionValue(mixed $value): mixed
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value)?->translatedFormat($this->getFormat()) ?? $value;
        } catch (InvalidFormatException) {
            return $value;
        }
    }

    public function getConditionOperators(): array
    {
        $labels = [
            ConditionOperator::EQUALS->value => 'date_on',
            ConditionOperator::NOT_EQUALS->value => 'date_not_on',
            ConditionOperator::LESS_THAN->value => 'date_before',
            ConditionOperator::AT_MOST->value => 'date_on_or_before',
            ConditionOperator::GREATER_THAN->value => 'date_after',
            ConditionOperator::AT_LEAST->value => 'date_on_or_after',
        ];

        return [
            ...array_map(fn (string $label): string => __("filament-form-builder::fields.{$label}"), $labels),
            ...ConditionOperator::options([ConditionOperator::EMPTY, ConditionOperator::NOT_EMPTY]),
        ];
    }

    public function getConditionPhrase(ConditionOperator $operator): string
    {
        return $operator->needsValue() ? "date_{$operator->value}" : $operator->value;
    }

    public function getFilterConstraints(): array
    {
        return [AnswerConstraints::date($this->getKey(), $this->getColumnLabel())];
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedCalendarDays;
    }

    public static function getFields(): array
    {
        return [
            ...static::getDefaultFields(),
            Select::make('format')
                ->label(__('filament-form-builder::fields.date_format'))
                ->helperText(__('filament-form-builder::fields.date_format_helper'))
                ->options(fn (): array => array_combine(self::FORMATS, array_map(fn (string $format): string => Carbon::now()->translatedFormat($format), self::FORMATS)))
                ->default(self::FORMATS[0])
                ->selectablePlaceholder(false),
        ];
    }
}
