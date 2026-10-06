<?php

namespace VanOns\FilamentFormBuilder\Filament\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;
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
     * @return array<ExportColumn>
     */
    public static function getColumnsFor(?Form $form): array
    {
        return [
            ExportColumn::make('form_id'),
            ExportColumn::make('form.title'),
            ExportColumn::make('submitter_email'),
            ...static::getAvailableDataExportColumns($form),
            ExportColumn::make('created_at'),
        ];
    }

    public function getCachedColumns(): array
    {
        return $this->cachedColumns ??= array_reduce(
            static::getColumnsFor($this->getExportedForm()),
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
     * The form's own fields first, in its order and under the labels the editor
     * typed, then whatever else the submissions hold.
     *
     * @return array<ExportColumn>
     */
    protected static function getAvailableDataExportColumns(?Form $form): array
    {
        $columns = [];

        foreach ($form?->getSubmissionFields() ?? [] as $key => $label) {
            $columns[$key] = ExportColumn::make("data.{$key}")->label($label);
        }

        // A field that was renamed or removed still has answers under its old
        // key. Going by the form alone would drop that column without a word.
        foreach (static::submittedDataKeys($form) as $key) {
            $columns[$key] ??= static::getExportColumn($key);
        }

        return array_values($columns);
    }

    /**
     * @return array<int, string>
     */
    protected static function submittedDataKeys(?Form $form): array
    {
        /** @var class-string<FormSubmission> $model */
        $model = static::$model;

        $query = $model::query();

        if ($form !== null) {
            $query->where('form_id', $form->getKey());
        }

        return $query
            ->pluck('data')
            ->flatMap(fn ($data) => array_keys($data ?? []))
            ->unique()
            ->values()
            ->all();
    }

    protected static function getExportColumn(string $key): ExportColumn
    {
        return ExportColumn::make("data.{$key}")
            ->label(str($key)->headline()->toString());
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
