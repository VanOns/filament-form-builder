<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource\Pages;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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

    public static function getNavigationGroup(): ?string
    {
        if (config('filament-form-builder.add_nav_group')) {
            return __('filament-form-builder::general.navigation-group');
        }

        return null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('form.title')
                    ->label(__('filament-form-builder::general.form_title'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('submitter_email')
                    ->label(__('filament-form-builder::general.submitter_email'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('filament-form-builder::general.created_at'))
                    ->sortable()
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('form')
                    ->label(__('filament-form-builder::general.form_title'))
                    ->relationship('form', 'title')
                    ->searchable()
                    ->multiple()
                    ->preload(),
                SelectFilter::make('submitter_email')
                    ->label(__('filament-form-builder::general.submitter_email'))
                    ->options(function (FormSubmission $model) {
                        return $model::query()
                            ->distinct('submitter_email')
                            ->whereNotNull('submitter_email')
                            ->pluck('submitter_email')
                            ->mapWithKeys(fn ($email) => [$email => $email])
                            ->toArray();
                    })
                    ->multiple()
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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

    /**
     * @return Builder<FormSubmission>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
