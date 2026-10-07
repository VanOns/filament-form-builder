<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources\FormResource\RelationManagers;

use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use VanOns\FilamentFormBuilder\Filament\Exporters\FormSubmissionExporter;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;
use VanOns\FilamentFormBuilder\Filament\Tables\FormSubmissionColumns;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;
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
            ->modifyQueryUsing(fn ($query) => $query->withoutGlobalScopes([SoftDeletingScope::class])->with('form'))
            ->defaultSort('created_at', 'desc')
            ->columns($columns->columns())
            ->filters($columns->filters(), layout: FiltersLayout::Modal)
            ->filtersFormWidth(Width::ThreeExtraLarge)
            ->filtersTriggerAction(function (Action $action): Action {
                // Resetting the rules is undone with one click, so it need not look dangerous.
                $reset = $action->getExtraModalFooterActions()['resetFilters'] ?? null;

                if ($reset instanceof Action) {
                    $reset->color('gray');
                }

                return $action
                    ->button()
                    ->label(__('filament-form-builder::general.filters.trigger'))
                    ->modalHeading(__('filament-form-builder::general.filters.heading'))
                    ->modalDescription(__('filament-form-builder::general.filters.description'))
                    ->modalIcon(Heroicon::OutlinedFunnel)
                    // No sticky header or footer: either makes the window scroll, and that clips the rule picker.
                    ->extraModalWindowAttributes(['class' => 'ffb-submission-filters']);
            })
            ->persistFiltersInSession()
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\DeleteAction::make(),
                Actions\ForceDeleteAction::make(),
                Actions\RestoreAction::make(),
            ])
            ->headerActions([
                Actions\ExportAction::make()
                    ->label(__('filament-form-builder::general.export_form_submissions'))
                    ->visible(fn (): bool => FilamentFormBuilderPlugin::get()->hasExportAction())
                    ->beforeFormFilled(function () use ($form): void {
                        FormSubmissionExporter::$form = $form;
                    })
                    ->options(['form_id' => $form->getKey()])
                    ->exporter(FormSubmissionExporter::class),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                    Actions\ExportBulkAction::make()
                        ->label(__('filament-form-builder::general.export_form_submissions'))
                        ->visible(fn (): bool => FilamentFormBuilderPlugin::get()->hasExportAction())
                        ->beforeFormFilled(function () use ($form): void {
                            FormSubmissionExporter::$form = $form;
                        })
                        ->options(['form_id' => $form->getKey()])
                        ->exporter(FormSubmissionExporter::class),
                ]),
            ]);
    }
}
