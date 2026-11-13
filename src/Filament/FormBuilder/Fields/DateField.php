<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use VanOns\FilamentFormBuilder\Enums\ConditionOperator;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;
use VanOns\FilamentFormBuilder\Rules\DateBetween;

/**
 * A date, stored the way a date input sends it (2026-10-07), so it sorts and
 * compares as text and reads as a date wherever it is shown. It can have an
 * earliest and a latest date, each a fixed one, today, or the stricter of both.
 */
class DateField extends InputField
{
    /**
     * How an answer reads, the first by default.
     */
    public const FORMATS = ['j F Y', 'd-m-Y', 'j M Y', 'Y-m-d'];

    public static string $previewView = 'filament-form-builder::filament.previews.date';

    public ?string $format = null;

    public ?string $minDate = null;

    public ?string $maxDate = null;

    public ?bool $notInPast = false;

    public ?bool $notInFuture = false;

    /**
     * The earliest date an answer may be, as `2026-10-07`.
     */
    public function minDate(?string $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    /**
     * The latest date an answer may be, as `2026-10-07`.
     */
    public function maxDate(?string $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    /**
     * Today or later, such as the day of an appointment.
     */
    public function notInPast(bool $condition = true): static
    {
        $this->notInPast = $condition;

        return $this;
    }

    /**
     * Today or earlier, such as a date of birth.
     */
    public function notInFuture(bool $condition = true): static
    {
        $this->notInFuture = $condition;

        return $this;
    }

    public function getMinDate(): ?string
    {
        $dates = array_filter([self::toDate($this->minDate), $this->notInPast ? Carbon::today()->toDateString() : null]);

        return $dates === [] ? null : max($dates);
    }

    public function getMaxDate(): ?string
    {
        $dates = array_filter([self::toDate($this->maxDate), $this->notInFuture ? Carbon::today()->toDateString() : null]);

        return $dates === [] ? null : min($dates);
    }

    /**
     * The date of a stored setting, which a date picker may hand over with a time.
     */
    protected static function toDate(?string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $value, $matches) === 1 ? $matches[0] : null;
    }

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
        $min = $this->getMinDate();
        $max = $this->getMaxDate();

        return [
            'date_format:Y-m-d',
            ...($min === null && $max === null ? [] : [
                new DateBetween($min, $max, fn (string $date): string => (string) $this->formatSubmissionValue($date)),
            ]),
        ];
    }

    public function getAttributesList(bool $withRequired = true): array
    {
        return [
            ...parent::getAttributesList($withRequired),
            ...array_filter([
                'min' => $this->getMinDate(),
                'max' => $this->getMaxDate(),
            ], fn (?string $value): bool => $value !== null),
        ];
    }

    public function formatSubmissionValue(mixed $value): mixed
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);

            // Carbon 2 returns false where Carbon 3 returns null.
            return $date instanceof Carbon ? $date->translatedFormat($this->getFormat()) : $value;
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

    public function getConditionValueComponent(): Field
    {
        return DatePicker::make('value');
    }

    public function getFilterConstraints(): array
    {
        return [AnswerConstraints::date($this->getKey(), $this->getColumnLabel())];
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedCalendarDays;
    }

    // A date input has no placeholder.
    public static function editableSettings(): array
    {
        return ['label', 'description'];
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

    public static function getValidationFields(): array
    {
        return [
            DatePicker::make('minDate')
                ->label(__('filament-form-builder::fields.earliest_date')),

            DatePicker::make('maxDate')
                ->label(__('filament-form-builder::fields.latest_date'))
                ->minDate(fn (Get $get): ?string => self::toDate($get('minDate'))),

            Toggle::make('notInPast')
                ->label(__('filament-form-builder::fields.not_in_past')),

            Toggle::make('notInFuture')
                ->label(__('filament-form-builder::fields.not_in_future')),
        ];
    }
}
