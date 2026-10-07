<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources;

use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use VanOns\FilamentFormBuilder\Filament\Exporters\FormSubmissionExporter;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource\Pages;
use VanOns\FilamentFormBuilder\Filament\Tables\FormSubmissionColumns;
use VanOns\FilamentFormBuilder\Filament\Tables\ReadStatus;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])->with(['form' => fn ($q) => $q->withTrashed()]);
    }

    public static function getModelLabel(): string
    {
        return trans_choice('filament-form-builder::general.models.form-submission.label', 1);
    }

    public static function getPluralModelLabel(): string
    {
        return trans_choice('filament-form-builder::general.models.form-submission.label', 2);
    }

    public static function getNavigationLabel(): string
    {
        return trans_choice('filament-form-builder::general.models.form-submission.label', 2);
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = FormSubmission::query()->whereNull('read_at')->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('filament-form-builder::general.submission.unread_badge');
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentFormBuilderPlugin::get()->getNavigationGroup();
    }

    public static function table(Table $table): Table
    {
        return ReadStatus::apply($table)
            ->defaultSort('created_at', 'desc')
            ->columns([
                ReadStatus::column(),
                TextColumn::make('form.title')
                    ->label(__('filament-form-builder::general.form_title'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('filament-form-builder::general.created_at'))
                    ->sortable()
                    ->dateTime()
                    ->toggleable(),
                ...FormSubmissionColumns::detailColumns(),
            ])
            ->filters([
                ReadStatus::filter(),
                SelectFilter::make('form')
                    ->label(__('filament-form-builder::general.form_title'))
                    ->relationship('form', 'title')
                    ->searchable()
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                // Over to that one form, where its own fields are columns.
                Actions\Action::make('formSubmissions')
                    ->label(__('filament-form-builder::general.view_submissions_of_form'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->iconButton()
                    ->tooltip(__('filament-form-builder::general.view_submissions_of_form'))
                    ->visible(fn (FormSubmission $record): bool => $record->form !== null)
                    ->url(fn (FormSubmission $record): string => FormResource::recordUrl($record->form, ['tab' => 'submissions'])),
                Actions\ViewAction::make(),
                Actions\ForceDeleteAction::make(),
                Actions\RestoreAction::make(),
            ])
            ->headerActions([
                Actions\ExportAction::make()
                    ->label(__('filament-form-builder::general.export_form_submissions'))
                    ->visible(fn (): bool => FilamentFormBuilderPlugin::get()->hasExportAction())
                    ->beforeFormFilled(fn () => FormSubmissionExporter::$form = null)
                    ->exporter(FormSubmissionExporter::class),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    ...ReadStatus::bulkActions(),
                    Actions\DeleteBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                    Actions\ExportBulkAction::make()
                        ->beforeFormFilled(fn () => FormSubmissionExporter::$form = null)
                        ->label(__('filament-form-builder::general.export_form_submissions'))
                        ->visible(fn (): bool => FilamentFormBuilderPlugin::get()->hasExportAction())
                        ->exporter(FormSubmissionExporter::class),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormSubmissions::route('/'),
            'view' => Pages\ViewFormSubmission::route('/{record}'),
        ];
    }
}
