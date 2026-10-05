<?php

namespace VanOns\FilamentFormBuilder\Filament\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionExporter extends Exporter
{
    /**
     * @var Collection<int, int> $selectedRecords
     */
    public static Collection $selectedRecords;

    /**
     * The form being exported, when the export runs from its own submissions
     * page. Its fields decide the columns, so a form is not handed the keys of
     * every other form on the site.
     */
    public static ?Form $form = null;

    protected static ?string $model = FormSubmission::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('form_id'),
            ExportColumn::make('form.title'),
            ExportColumn::make('submitter_email'),
            ...static::getAvailableDataExportColumns(),
            ExportColumn::make('created_at'),
        ];
    }

    /**
     * The form's own fields first, in its order and under the labels the editor
     * typed, then whatever else the submissions hold.
     *
     * @return array<ExportColumn>
     */
    protected static function getAvailableDataExportColumns(): array
    {
        $columns = [];

        foreach (static::$form?->getSubmissionFields() ?? [] as $key => $label) {
            $columns[$key] = ExportColumn::make("data.{$key}")->label($label);
        }

        // A field that was renamed or removed still has answers under its old
        // key. Going by the form alone would drop that column without a word.
        foreach (static::submittedDataKeys() as $key) {
            $columns[$key] ??= static::getExportColumn($key);
        }

        return array_values($columns);
    }

    /**
     * @return array<int, string>
     */
    protected static function submittedDataKeys(): array
    {
        /** @var class-string<FormSubmission> $model */
        $model = static::$model;

        $query = $model::query();

        if (static::$form !== null) {
            $query->where('form_id', static::$form->getKey());
        }

        if (isset(static::$selectedRecords)) {
            $query->whereIn('id', static::$selectedRecords);
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
        $keyLabel = str($key)
            ->replaceFirst('key_', '')
            ->headline()
            ->toString();

        return ExportColumn::make("data.{$key}")
            ->label($keyLabel);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your product export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
