<?php

namespace VanOns\FilamentFormBuilder\Filament\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Classes\SubmissionAnswer;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionExporter extends Exporter
{
    /**
     * The form whose submissions the export modal offers columns for, so a form
     * is not handed the keys of every other form on the site. The export job
     * runs in another process and finds the form through the `form_id` option.
     */
    public static ?Form $form = null;

    protected static ?string $model = FormSubmission::class;

    public static function getColumns(): array
    {
        return static::getColumnsFor(static::$form);
    }

    /**
     * @param  array<int, string>|null  $dataKeys  the keys an export across forms has columns for
     * @return array<ExportColumn>
     */
    public static function getColumnsFor(?Form $form, ?array $dataKeys = null): array
    {
        return [
            ExportColumn::make('form_id'),
            ExportColumn::make('form.title'),
            ...static::getAvailableDataExportColumns($form, $dataKeys),
            ExportColumn::make('created_at'),
        ];
    }

    /**
     * Every chunk of the export builds these again, so an export across forms
     * takes its keys from the columns that were picked instead of from every
     * submission there is.
     */
    public function getCachedColumns(): array
    {
        if (isset($this->cachedColumns)) {
            return $this->cachedColumns;
        }

        $dataKeys = array_map(
            fn (string $name): string => Str::after($name, 'data.'),
            array_values(array_filter(array_keys($this->columnMap), fn (string $name): bool => str_starts_with($name, 'data.'))),
        );

        return $this->cachedColumns = array_reduce(
            static::getColumnsFor($this->getExportedForm(), $dataKeys),
            function (array $carry, ExportColumn $column): array {
                $carry[$column->getName()] = $column->exporter($this);

                return $carry;
            },
            [],
        );
    }

    protected function getExportedForm(): ?Form
    {
        $formId = $this->getOptions()['form_id'] ?? null;

        return $formId === null ? null : Form::withTrashed()->find($formId);
    }

    /**
     * One form exports its own fields, in its order and under the labels the
     * editor typed, and whatever else its submissions hold in one column. An
     * export across forms has a column for every key the submissions hold.
     *
     * @param  array<int, string>|null  $dataKeys
     * @return array<ExportColumn>
     */
    protected static function getAvailableDataExportColumns(?Form $form, ?array $dataKeys = null): array
    {
        if ($form === null) {
            return array_map(
                fn (string $key): ExportColumn => static::getExportColumn($key, str($key)->headline()->toString()),
                $dataKeys ?? static::submittedDataKeys(),
            );
        }

        $columns = [];

        foreach ($form->getSubmissionFields() as $key => $label) {
            $columns[] = static::getExportColumn($key, $label);
        }

        $columns[] = ExportColumn::make('other_data')
            ->label(__('filament-form-builder::general.submission.other_data'))
            ->state(fn (FormSubmission $record): ?string => static::getOtherData($record));

        return $columns;
    }

    /**
     * The answers to fields the form no longer has, so an export loses
     * nothing, one per line under the label they had.
     */
    protected static function getOtherData(FormSubmission $record): ?string
    {
        $lines = array_map(
            fn (SubmissionAnswer $answer): string => $answer->label . ': ' . FormSubmission::toText($answer->value ?? $answer->files),
            $record->getRemovedAnswers(),
        );

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    protected static function submittedDataKeys(): array
    {
        /** @var class-string<FormSubmission> $model */
        $model = static::$model;

        return $model::query()
            ->get(['data', 'files'])
            ->flatMap(fn (FormSubmission $submission): array => [
                ...array_keys($submission->data ?? []),
                ...array_keys($submission->files ?? []),
            ])
            ->unique()
            ->values()
            ->all();
    }

    protected static function getExportColumn(string $key, string $label): ExportColumn
    {
        return ExportColumn::make("data.{$key}")
            ->label($label)
            ->state(fn (FormSubmission $record): ?string => $record->getDisplayText($key));
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = trans_choice('filament-form-builder::general.export_completed', $export->successful_rows, [
            'count' => Number::format($export->successful_rows),
        ]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . trans_choice('filament-form-builder::general.export_failed_rows', $failedRowsCount, [
                'count' => Number::format($failedRowsCount),
            ]);
        }

        return $body;
    }
}
