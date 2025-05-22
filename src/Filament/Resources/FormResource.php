<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use VanOns\FilamentFormBuilder\Enums\SubmitNotificationType;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\FormBuilder;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages;
use VanOns\FilamentFormBuilder\Models\Form as FormModel;

class FormResource extends Resource
{
    protected static ?string $model = FormModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getModelLabel(): string
    {
        return trans_choice('filament-form-builder::general.models.form.label', 1);
    }

    public static function getPluralModelLabel(): string
    {
        return trans_choice('filament-form-builder::general.models.form.label', 2);
    }

    public static function getNavigationLabel(): string
    {
        return trans_choice('filament-form-builder::general.models.form.label', 2);
    }

    public static function getNavigationGroup(): ?string
    {
        if (config('filament-form-builder.add_nav_group')) {
            return __('filament-form-builder::general.navigation-group');
        }

        return null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                static::getGeneralSection(),
                static::getCustomSection(),
                static::getSubmitNotificationSection(),
                static::getEmailNotificationSection(),
            ]);
    }

    public static function getGeneralSection(): Section
    {
        return Section::make()
            ->schema([
                Select::make('template')
                    ->required()
                    ->label(__('filament-form-builder::general.template'))
                    ->options([
                        'custom' => __('filament-form-builder::fields.custom_form_builder'),
                        'test' => 'test',
                        ...self::getFormTemplates(),
                    ])
                    ->live()
                    ->columnSpan(1),
                TextInput::make('title')
                    ->label(__('filament-form-builder::general.title'))
                    ->required()
                    ->columnSpan(1),
            ])->columns();
    }

    public static function getCustomSection(): Section
    {
        return Section::make(__('filament-form-builder::general.custom_form'))
            ->collapsible()
            ->visible(fn (array $state) => ($state['template'] ?? null) === 'custom')
            ->schema([
                FormBuilder::make('custom'),
            ])->columnSpanFull();
    }

    public static function getSubmitNotificationSection(): Section
    {
        return Section::make(__('filament-form-builder::general.submit_notification'))
            ->schema([
                Select::make('submit_notification_type')
                    ->label(__('filament-form-builder::general.submit_notification_type'))
                    ->options(SubmitNotificationType::class)
                    ->required()
                    ->live()
                    ->columnSpan(1),
                TextInput::make('submit_notification_content')
                    ->label(__('filament-form-builder::general.url'))
                    ->url()
                    ->hidden(fn (Get $get) => $get('submit_notification_type') !== SubmitNotificationType::URL->value)
                    ->required(fn (Get $get) => $get('submit_notification_type') === SubmitNotificationType::URL->value)
                    ->columnSpanFull(),
                RichEditor::make('submit_notification_content')
                    ->label(__('filament-form-builder::general.content'))
                    ->hidden(fn (Get $get) => $get('submit_notification_type') !== SubmitNotificationType::Content->value)
                    ->required(fn (Get $get) => $get('submit_notification_type') === SubmitNotificationType::Content->value)
                    ->columnSpanFull(),
            ])->columns();
    }

    public static function getEmailNotificationSection(): Section
    {
        return Section::make(__('filament-form-builder::general.email_notification'))
            ->schema([
                Toggle::make('notification_enabled')
                    ->live()
                    ->label(__('filament-form-builder::general.notification_enabled'))
                    ->default(true)
                    ->columnSpanFull(),
                TextInput::make('notification_subject')
                    ->visible(fn (Get $get) => $get('notification_enabled') == true)
                    ->label(__('filament-form-builder::general.notification_subject'))
                    ->required()
                    ->columnSpan(1),
                TextInput::make('notification_sender')
                    ->visible(fn (Get $get) => $get('notification_enabled') == true)
                    ->label(__('filament-form-builder::general.notification_sender'))
                    ->default(config('mail.from.address'))
                    ->email()
                    ->required()
                    ->columnSpan(1),
                RichEditor::make('notification_content')
                    ->visible(fn (Get $get) => $get('notification_enabled') == true)
                    ->label(__('filament-form-builder::general.notification_content'))
                    ->required()
                    ->columnSpanFull(),
                Repeater::make('notification_receivers')
                    ->visible(fn (Get $get) => $get('notification_enabled') == true)
                    ->label(__('filament-form-builder::general.notification_receivers'))
                    ->simple(
                        TextInput::make('email')
                            ->email()
                            ->required(),
                    )
                    ->grid()
                    ->columnSpanFull(),
            ])->columns();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament-form-builder::general.title'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('template')
                    ->state(fn (FormModel $record) => $record->getTemplateLabel())
                    ->label(__('filament-form-builder::general.template')),
                IconColumn::make('notification_enabled')
                    ->label(__('filament-form-builder::general.notification_enabled'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('submissions_count')
                    ->label(__('filament-form-builder::general.submissions'))
                    ->counts('submissions')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('last_submission')
                    ->label(__('filament-form-builder::general.last_submission'))
                    ->state(function (FormModel $form) {
                        return $form
                            ->submissions()
                            ->orderBy('created_at', 'desc')
                            ->take(1)
                            ->first()
                            ?->created_at;
                    })->since()
                    ->toggleable(),
                TextColumn::make('receiver_count')
                    ->label(__('filament-form-builder::general.notification_receiver_count'))
                    ->state(fn (Model $record) => count($record->notification_receivers ?? []))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('filament-form-builder::general.created_at'))
                    ->sortable()
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament-form-builder::general.updated_at'))
                    ->sortable()
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('notification_enabled')
                    ->label(__('filament-form-builder::general.notification_enabled')),
                SelectFilter::make('template')
                    ->label(__('filament-form-builder::general.template'))
                    ->options(function () {
                        return FormModel::query()
                            ->distinct('template')
                            ->pluck('template')
                            ->mapWithKeys(function ($template) {
                                return [$template => config("filament-form-builder.templates.{$template}", 'not-found')];
                            })
                            ->toArray();
                    })
                    ->multiple()
                    ->searchable(),
                TrashedFilter::make(),
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
            'index' => Pages\ListForms::route('/'),
            'create' => Pages\CreateForm::route('/create'),
            'edit' => Pages\EditForm::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function getFormTemplates(): array
    {
        return collect(config('filament-form-builder.templates'))
            ->toArray();
    }

    /**
     * @return Builder<FormModel>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
