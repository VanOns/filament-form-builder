<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources;

use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Contracts\FilamentForm;
use VanOns\FilamentFormBuilder\Enums\SubmitNotificationType;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Actions\ModalAction;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\FormBuilder;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages;
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
                static::getGeneralSection()
                    ->columnSpanFull(),
                static::getCustomSection()
                    ->columnSpanFull(),
                static::getSubmitNotificationSection()
                    ->columnSpanFull(),
                Group::make([
                    static::getEmailNotificationSection(),
                    static::getIntegrationsSections(),
                ])->columnSpanFull()->columns([
                    'xl' => 2,
                ]),
            ]);
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
        $visible = function (array $state) {
            if (!$template = $state['template']) {
                return false;
            }

            return class_exists($template)
                && is_subclass_of($template, FilamentForm::class)
                && $template::isCustom();
        };

        return Section::make(__('filament-form-builder::general.custom_form'))
            ->collapsible()
            ->visible(fn (array $state) => $visible($state))
            ->schema([
                FormBuilder::make('custom'),
            ])->columnSpanFull();
    }

    public static function getSubmitNotificationSection(): Section
    {
        return Section::make(__('filament-form-builder::general.submit_notification'))
            ->schema([
                ToggleButtons::make('submit_notification_type')
                    ->required()
                    ->label(__('filament-form-builder::general.what_happens_after_submission'))
                    ->options(SubmitNotificationType::class)
                    ->default(SubmitNotificationType::URL)
                    ->live()
                    ->columnSpan(1)
                    ->grouped()
                    ->afterStateUpdated(fn (Set $set) => $set('submit_notification_content', null)),
                Group::make(function (Get $get) {
                    $isType = fn (SubmitNotificationType $type) => $get('submit_notification_type') === $type;

                    return [
                        $isType(SubmitNotificationType::URL) ? TextInput::make('submit_notification_content')
                            ->label(__('filament-form-builder::general.url'))
                            ->required()
                            ->placeholder(__('filament-form-builder::general.form_redirect_example', ['url' => 'https://example.com/form-confirmation']))
                            ->suffixIcon(Heroicon::OutlinedLink)
                            ->columnSpanFull() : null,
                        $isType(SubmitNotificationType::Content) ? RichEditor::make('submit_notification_content')
                            ->label(__('filament-form-builder::general.content'))
                            ->required()
                            ->placeholder(__('filament-form-builder::general.form_submitted_successfully'))
                            ->columnSpanFull() : null,
                    ];
                })->columnSpanFull(),
            ])->columns();
    }

    public static function getEmailNotificationSection(): Section
    {
        return Section::make(__('filament-form-builder::general.email_notifications'))
            ->visible(self::hasNotificationsEnabled(...))
            ->schema([
                Repeater::make('notifications')
                    ->label(__('filament-form-builder::general.email_notifications'))
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->columns(3)
                    ->collapsed()
                    ->default([])
                    ->extraItemActions([
                        ModalAction::make('placeholders')
                            ->hidden(fn (?FormModel $record) => is_null($record))
                            ->modalContent(function (Get $get, ?FormModel $record) {
                                /**
                                 * @var null|string|class-string<FilamentForm> $template
                                 */
                                $template = $get('template');
                                return (isset($template) && is_subclass_of($template, FilamentForm::class))
                                    ? $record?->getFormComponent()->getPlaceholdersHtmlString()
                                    : new HtmlString(__('filament-form-builder::general.unknown'));
                            }),
                    ])
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
                        Callout::make(__('filament-form-builder::general.notifications.sender_callout'))
                            ->warning()
                            ->columnSpanFull(),
                        TextInput::make('sender')
                            ->columnSpan(2)
                            ->helperText(__('filament-form-builder::general.notifications.sender_hint'))
                            ->label(__('filament-form-builder::general.notifications.sender'))
                            ->placeholder(config('mail.from.address', 'email@example.com'))
                            ->email(),
                        Repeater::make('receivers')
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

    public static function getIntegrationsSections(): Section
    {
        $options = fn () => Integration::getOptionList();
        return Section::make(__('filament-form-builder::general.integrations'))
            ->description(__('filament-form-builder::general.integrations_explanation'))
            ->visible(self::hasIntegrationsEnabled(...))
            ->icon('heroicon-o-server-stack')
            ->iconSize(IconSize::ExtraLarge)
            ->schema([
                Repeater::make('integrations')
                    ->label(__('filament-form-builder::general.integrations'))
                    ->hiddenLabel()
                    ->itemLabel(fn (array $state) => isset($state['class']) ? ($options()[$state['class']] ?? $state['class']) : __('filament-form-builder::general.integration'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->collapsed()
                    ->reactive()
                    ->default([])
                    ->schema([
                        Select::make('class')
                            ->label(__('filament-form-builder::general.integration'))
                            ->hiddenLabel()
                            ->required()
                            ->reactive()
                            ->columnSpan(2)
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForms::route('/'),
            'create' => Pages\CreateForm::route('/create'),
            'edit' => Pages\EditForm::route('/{record}/edit'),
        ];
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
     * @return array<string, string>
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
}
