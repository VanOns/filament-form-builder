<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources;

use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Contracts\FilamentForm;
use VanOns\FilamentFormBuilder\Enums\SubmitNotificationType;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\FormBuilder;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;
use VanOns\FilamentFormBuilder\Models\Form as FormModel;
use VanOns\FilamentFormBuilder\View\Components\FormComponent;

class FormResource extends Resource
{
    protected static ?string $model = FormModel::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->contained(false)
                    ->tabs([
                        Tabs\Tab::make(__('filament-form-builder::general.general'))
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                static::getGeneralSection(),
                                static::getCustomSection(),
                                static::getSubmitNotificationSection()
                                    ->hidden(fn (Get $get) => empty($get('template'))),
                            ]),
                        Tabs\Tab::make(__('filament-form-builder::general.settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->visible(self::hasSettings(...))
                            ->schema([
                                static::getSettingsSection()
                                    ->columns(),
                            ]),
                        Tabs\Tab::make(__('filament-form-builder::general.email_notification'))
                            ->icon('heroicon-o-bell-alert')
                            ->visible(self::hasNotificationsEnabled(...))
                            ->schema([
                                static::getPlaceholdersSection(),
                                static::getEmailNotificationSection(),
                            ]),
                        Tabs\Tab::make(__('filament-form-builder::general.integrations'))
                            ->icon('heroicon-o-server-stack')
                            ->visible(self::hasIntegrationsEnabled(...))
                            ->schema([
                                static::getIntegrationsSection(),
                            ]),
                    ]),
            ]);
    }

    public static function getSettingsSection(): Section
    {
        return Section::make(__('filament-form-builder::general.settings'))
            ->description(__('filament-form-builder::general.settings_explanation'))
            ->icon('heroicon-o-cog-6-tooth')
            ->statePath('settings')
            ->schema(function (Get $get): array {
                $template = $get('template');

                if (!isset($template) || !is_subclass_of($template, FilamentForm::class)) {
                    return [];
                }

                return $template::settings();
            });
    }

    public static function getGeneralSection(): Section
    {
        return Section::make()
            ->schema([
                Select::make('template')
                    ->required()
                    ->label(__('filament-form-builder::general.template'))
                    ->searchable()
                    ->options(FormComponent::getTemplates())
                    ->live()
                    ->columnSpan(1),
                TextInput::make('title')
                    ->label(__('filament-form-builder::general.title'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpan(1),
            ])->columns();
    }

    public static function getCustomSection(): Section
    {
        return Section::make(__('filament-form-builder::general.custom_form'))
            ->description(__('filament-form-builder::general.custom_form_explanation'))
            ->icon('heroicon-o-cube')
            ->visible(self::hasCustomFields(...))
            ->schema([
                FormBuilder::make('custom'),
            ])->columnSpanFull();
    }

    public static function getSubmitNotificationSection(): Section
    {
        return Section::make(__('filament-form-builder::general.submit_notification'))
            ->description(__('filament-form-builder::general.submit_notification_explanation'))
            ->icon('heroicon-o-paper-airplane')
            ->schema([
                ToggleButtons::make('submit_notification_type')
                    ->required()
                    ->label(__('filament-form-builder::general.what_happens_after_submission'))
                    ->options(SubmitNotificationType::class)
                    ->default(SubmitNotificationType::URL)
                    ->live()
                    ->columnSpan(1)
                    ->grouped(),
                Group::make(FilamentFormBuilderPlugin::getRedirectSchema())
                    ->visible(fn (Get $get) => $get('submit_notification_type') === SubmitNotificationType::URL)
                    ->columnSpanFull(),
                RichEditor::make('submit_notification_content')
                    ->label(__('filament-form-builder::general.content'))
                    ->required()
                    ->placeholder(__('filament-form-builder::general.form_submitted_successfully'))
                    ->visible(fn (Get $get) => $get('submit_notification_type') === SubmitNotificationType::Content)
                    ->columnSpanFull(),
            ])->columns();
    }

    public static function getEmailNotificationSection(): Section
    {
        return Section::make(__('filament-form-builder::general.email_notifications'))
            ->description(__('filament-form-builder::general.email_notification_explanation'))
            ->visible(self::hasNotificationsEnabled(...))
            ->icon('heroicon-o-bell-alert')
            ->schema([
                Callout::make(__('filament-form-builder::general.notifications.sender_callout'))
                    ->warning()
                    ->columnSpanFull(),
                Repeater::make('notifications')
                    ->label(__('filament-form-builder::general.email_notifications'))
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->columns(3)
                    ->grid([
                        '2xl' => 2,
                    ])
                    ->collapsed()
                    ->default([])
                    ->afterStateHydrated(static function (Component $component, ?array $rawState): void {
                        $component->rawState(
                            collect($rawState ?? [])
                                ->mapWithKeys(fn ($itemData) => [(string) Str::uuid() => $itemData])
                                ->toArray(),
                        );
                    })
                    ->itemLabel(function (array $state) {
                        if ($subject = $state['subject'] ?? null) {
                            return "{$subject} - " . __('filament-form-builder::general.email_notification');
                        }
                        return __('filament-form-builder::general.email_notification');
                    })
                    ->schema([
                        TextInput::make('subject')
                            ->columnSpanFull()
                            ->label(__('filament-form-builder::general.notifications.subject'))
                            ->required(),
                        RichEditor::make('content')
                            ->columnSpanFull()
                            ->label(__('filament-form-builder::general.notifications.content'))
                            ->required(),
                        TextInput::make('sender')
                            ->columnSpan(2)
                            ->helperText(__('filament-form-builder::general.notifications.sender_hint'))
                            ->label(__('filament-form-builder::general.notifications.sender'))
                            ->placeholder(config('mail.from.address', 'email@example.com'))
                            ->email(),
                        Repeater::make('receivers')
                            ->reorderable(false)
                            ->columnStart(1)
                            ->columnSpanFull()
                            ->label(__('filament-form-builder::general.notifications.receivers'))
                            ->hint(__('filament-form-builder::general.notifications.email_or_field_hint'))
                            ->grid(3)
                            ->addActionLabel(__('filament-form-builder::general.add'))
                            ->addActionAlignment(Alignment::Start)
                            ->default([])
                            ->simple(
                                TextInput::make('email')
                                    ->regex('/^\S*$/')
                                    ->placeholder(__('filament-form-builder::general.notifications.email_or_field'))
                                    ->required(),
                            ),
                    ]),
            ])->columns();
    }

    public static function getPlaceholdersSection(): Section
    {
        return Section::make(__('filament-form-builder::general.placeholders'))
            ->description(__('filament-form-builder::general.placeholders_explanation'))
            ->icon('heroicon-o-list-bullet')
            ->collapsed()
            ->collapsible()
            ->visible(fn (?FormModel $record) => !is_null($record))
            ->schema([
                Text::make(__('filament-form-builder::general.placeholders_copy_hint'))
                    ->color('gray')
                    ->size(TextSize::Small),
                TextEntry::make('placeholders')
                    ->hiddenLabel()
                    ->badge()
                    ->copyable()
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder(__('filament-form-builder::general.unknown'))
                    ->state(function (Get $get, ?FormModel $record): array {
                        /**
                         * @var null|string|class-string<FilamentForm> $template
                         */
                        $template = $get('template');
                        return (isset($template) && is_subclass_of($template, FilamentForm::class))
                            ? $record?->getFormComponent()->getPlaceholderList() ?? []
                            : [];
                    }),
                Text::make(__('filament-form-builder::general.placeholders_receivers_hint'))
                    ->color('gray')
                    ->size(TextSize::Small),
            ]);
    }

    public static function getIntegrationsSection(): Section
    {
        $options = fn () => Integration::getOptionList();
        return Section::make(__('filament-form-builder::general.integrations'))
            ->description(__('filament-form-builder::general.integrations_explanation'))
            ->icon('heroicon-o-server-stack')
            ->iconSize(IconSize::ExtraLarge)
            ->schema([
                Repeater::make('integrations')
                    ->label(__('filament-form-builder::general.integrations'))
                    ->hiddenLabel()
                    ->itemLabel(fn (array $state) => isset($state['class']) ? ($options()[$state['class']] ?? $state['class']) : __('filament-form-builder::general.integration'))
                    ->columnSpanFull()
                    ->columns()
                    ->collapsed()
                    ->reactive()
                    ->default([])
                    ->afterStateHydrated(static function (Component $component, ?array $rawState): void {
                        $component->rawState(
                            collect($rawState ?? [])
                                ->mapWithKeys(fn ($itemData) => [(string) Str::uuid() => $itemData])
                                ->toArray(),
                        );
                    })
                    ->schema([
                        Select::make('class')
                            ->label(__('filament-form-builder::general.integration'))
                            ->hiddenLabel()
                            ->required()
                            ->reactive()
                            ->options($options),

                        Group::make(self::getIntegrationSchema(...))
                            ->columns()
                            ->columnSpanFull(),
                    ]),
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
            ->recordUrl(static::editOrViewUrl(...))
            ->filters([
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
                TrashedFilter::make()->default('with_trashed'),
            ])
            ->recordActions([
                Actions\ViewAction::make()
                    ->visible(fn (FormModel $record) => !static::canEdit($record)),
                Actions\EditAction::make(),
                Actions\ForceDeleteAction::make()
                    ->modalDescription(__('filament-form-builder::fields.form_force_deletion_warning')),
                Actions\RestoreAction::make(),
                Actions\ActionGroup::make([
                    Actions\ReplicateAction::make()
                        ->modalWidth(Width::ExtraLarge)
                        ->form([
                            TextInput::make('title')
                                ->label(__('filament-form-builder::general.title'))
                                ->unique()
                                ->required(),
                        ])
                        ->beforeReplicaSaved(function (FormModel $replica) {
                            $replica->offsetUnset('submissions_count');
                        }),
                    Actions\DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function editOrViewUrl(FormModel $record): string
    {
        return static::getUrl(static::canEdit($record) ? 'edit' : 'view', ['record' => $record]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForms::route('/'),
            'create' => Pages\CreateForm::route('/create'),
            'view' => Pages\ViewForm::route('/{record}'),
            'edit' => Pages\EditForm::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function hasNotificationsEnabled(Get $get): bool
    {
        $template = $get('template');
        if ((isset($template) && is_subclass_of($template, FilamentForm::class))) {
            return $template::hasNotifications();
        }

        return false;
    }

    public static function hasIntegrationsEnabled(Get $get): bool
    {
        $template = $get('template');
        if ((isset($template) && is_subclass_of($template, FilamentForm::class))) {
            return $template::hasIntegrations();
        }

        return false;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<int, Component>
     */
    public static function getIntegrationSchema(array $state): array
    {
        $integration = $state['class'] ?? null;
        if (isset($integration) && is_subclass_of($integration, Integration::class)) {
            $schema = $integration::schema();
        }

        return [
            ...$schema ?? [],
        ];
    }

    public static function hasSettings(Get $get): bool
    {
        $template = $get('template');

        return isset($template)
            && is_subclass_of($template, FilamentForm::class)
            && !empty($template::settings());
    }

    /**
     * @param array<string, mixed> $state
     */
    public static function hasCustomFields(array $state): bool
    {
        if (!$template = $state['template']) {
            return false;
        }

        return class_exists($template)
            && is_subclass_of($template, FilamentForm::class)
            && $template::isCustom();
    }
}
