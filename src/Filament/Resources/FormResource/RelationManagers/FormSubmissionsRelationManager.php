<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources\FormResource\RelationManagers;

use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use VanOns\FilamentFormBuilder\Filament\Exporters\FormSubmissionExporter;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;
use VanOns\FilamentFormBuilder\Filament\Tables\FormSubmissionColumns;
use VanOns\FilamentFormBuilder\Models\Form;

class FormSubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    // Hands the table the labels, the policies and the view URL of the
    // submissions resource, so this stays a view onto the same records.
    protected static ?string $relatedResource = FormSubmissionResource::class;

    public function table(Table $table): Table
    {
        /** @var Form $form */
        $form = $this->getOwnerRecord();
        $columns = FormSubmissionColumns::for($form);

        return $table
            // The tab is already labelled; a heading would say it twice.
            ->heading(null)
            ->modifyQueryUsing(fn ($query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->defaultSort('created_at', 'desc')
            ->columns($columns->columns())
            ->filters($columns->filters())
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\DeleteAction::make(),
                Actions\ForceDeleteAction::make(),
                Actions\RestoreAction::make(),
            ])
            ->headerActions([
                Actions\ExportAction::make()
                    ->label(__('filament-form-builder::general.export_form_submissions'))
                    ->visible(config('filament-form-builder.enable_export_action') === true)
                    ->beforeFormFilled(function () use ($form): void {
                        FormSubmissionExporter::$form = $form;
                    })
                    ->exporter(FormSubmissionExporter::class),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                    Actions\ExportBulkAction::make()
                        ->label(__('filament-form-builder::general.export_form_submissions'))
                        ->visible(config('filament-form-builder.enable_export_action') === true)
                        ->beforeFormFilled(function (Collection $records) use ($form): void {
                            FormSubmissionExporter::$form = $form;
                            FormSubmissionExporter::$selectedRecords = $records;
                        })
                        ->exporter(FormSubmissionExporter::class),
                ]),
            ]);
    }
}
