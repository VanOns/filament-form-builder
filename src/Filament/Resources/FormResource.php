<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources;

use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Classes\MergeTags;
use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\FormCanvas;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\MergeTagEditor;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\NotificationList;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\SubmitNotifications;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\Pages;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource\RelationManagers\FormSubmissionsRelationManager;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;
use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Helpers\FormTypeHelper;
use VanOns\FilamentFormBuilder\Models\Form as FormModel;

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
        return FilamentFormBuilderPlugin::get()->getNavigationGroup();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->contained(false)
                    ->persistTabInQueryString()
                    ->tabs([
                        Tabs\Tab::make(__('filament-form-builder::general.general'))
                            ->id('general')
                            ->key('general', isInheritable: false)
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                static::getGeneralSection(),
                                static::getCustomSection(),
                                static::getSubmitNotificationSection()
                                    ->hidden(fn (Get $get) => empty($get('template')) || empty(static::getSubmitNotificationTypes($get))),
                            ]),
                        Tabs\Tab::make(__('filament-form-builder::general.settings'))
                            ->id('settings')
                            ->key('settings', isInheritable: false)
                            ->icon('heroicon-o-cog-6-tooth')
                            ->visible(self::hasSettings(...))
                            ->schema([
                                static::getSettingsSection()
                                    ->columns(),
                            ]),
                        Tabs\Tab::make(__('filament-form-builder::general.email_notification'))
                            ->id('email-notification')
                            ->key('email-notification', isInheritable: false)
                            ->icon('heroicon-o-bell-alert')
                            ->visible(self::hasNotificationsEnabled(...))
                            ->schema([
                                static::getEmailNotificationSection(),
                            ]),
                        Tabs\Tab::make(__('filament-form-builder::general.integrations'))
                            ->id('integrations')
                            ->key('integrations', isInheritable: false)
                            ->icon('heroicon-o-server-stack')
                            ->visible(self::hasIntegrationsEnabled(...))
                            ->schema([
                                static::getIntegrationsSection(),
                            ]),
                        Tabs\Tab::make(__('filament-form-builder::general.submissions'))
                            ->id('submissions')
                            ->key('submissions', isInheritable: false)
                            ->icon('heroicon-o-clipboard-document-check')
                            ->visible(fn (?FormModel $record): bool => $record !== null)
                            ->schema(fn (?FormModel $record): array => $record === null ? [] : [
                                Livewire::make(FormSubmissionsRelationManager::class, [
                                    'ownerRecord' => $record,
                                    'pageClass' => Pages\EditForm::class,
                                ])->columnSpanFull(),
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
            ->schema(fn (Get $get, ?FormModel $record): array => static::getFormType($get, $record)?->settings() ?? []);
    }

    public static function getGeneralSection(): Section
    {
        $types = FormTypeHelper::options();
        $fixedTypes = array_diff_key($types, [FormTypeHelper::CUSTOM => true]);
        $hasCustom = isset($types[FormTypeHelper::CUSTOM]);
        // Most forms are built on the canvas, so a type from code sits behind a switch.
        $isFixed = fn (Get $get): bool => !$hasCustom || (bool) $get('fixed_type');

        return Section::make(__('filament-form-builder::general.form_details'))
            ->icon('heroicon-o-document-text')
            ->afterHeader($hasCustom && $fixedTypes !== [] ? [
                Toggle::make('fixed_type')
                    ->label(__('filament-form-builder::general.fixed_type'))
                    ->formatStateUsing(fn (?FormModel $record): bool => filled($record?->template) && $record->template !== FormTypeHelper::CUSTOM)
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(fn (bool $state, Set $set) => $set('template', $state ? array_key_first($fixedTypes) : FormTypeHelper::CUSTOM)),
            ] : [])
            ->schema([
                TextInput::make('title')
                    ->label(__('filament-form-builder::general.title'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpan(fn (Get $get): int | string => $isFixed($get) ? 2 : 'full'),
                Select::make('template')
                    ->required()
                    ->label(__('filament-form-builder::general.type'))
                    ->searchable()
                    ->selectablePlaceholder(false)
                    ->options(fn (Get $get): array => $hasCustom && $isFixed($get) ? $fixedTypes : $types)
                    ->default($hasCustom ? FormTypeHelper::CUSTOM : null)
                    ->live()
                    ->visible($isFixed)
                    ->dehydratedWhenHidden()
                    ->columnSpan(1),
            ])->columns(3);
    }

    public static function getCustomSection(): Section
    {
        return Section::make(fn (Get $get): string => static::hasCustomFields($get)
            ? __('filament-form-builder::general.custom_form')
            : __('filament-form-builder::general.form_fields'))
            ->description(fn (Get $get): string => static::hasCustomFields($get)
                ? __('filament-form-builder::general.custom_form_explanation')
                : __('filament-form-builder::general.form_fields_explanation'))
            ->icon('heroicon-o-cube')
            ->visible(fn (Get $get): bool => filled(static::getFormType($get)?->fields()))
            ->schema([
                FormCanvas::make('custom.fields')
                    ->hiddenLabel()
                    ->fixedFields(fn (Get $get, ?FormModel $record): array => static::getFormType($get, $record)?->fields() ?? [])
                    ->afterKeyRenamed(static::renameKeyInNotifications(...))
                    ->keyUsagesUsing(static::findKeyUsages(...)),
            ])->columnSpanFull();
    }

    /**
     * The notifications and outcomes that still name one of a field's keys.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function findKeyUsages(array $keys, Get $get, LivewireComponent $livewire): array
    {
        $labels = MergeTagEditor::form($livewire)->getMergeTags();
        $names = fn (mixed $content): bool => array_intersect(MergeTags::ids($content), $keys) !== [];
        $conditions = fn (array $item): bool => array_intersect(array_column($item['conditions'] ?? [], 'key'), $keys) !== [];
        $recipients = array_map(fn (string $key): string => EmailNotification::FIELD_PREFIX . $key, $keys);
        $usages = [];

        foreach ($get('notifications') ?? [] as $notification) {
            $isNamed = $conditions($notification)
                || array_intersect([...$notification['to'] ?? [], ...$notification['cc'] ?? [], ...$notification['bcc'] ?? [], $notification['reply_to'] ?? null], $recipients) !== []
                || array_filter(['subject', 'content', 'senderName'], fn (string $text): bool => $names($notification[$text] ?? null)) !== [];

            if ($isNamed) {
                $usages[] = __('filament-form-builder::general.canvas.usage_notification', ['subject' => MergeTags::render($notification['subject'] ?? null, $labels, asText: true)]);
            }
        }

        $outcomeNames = function (array $outcome) use ($names): bool {
            $query = $outcome['query'] ?? null;

            return $names($outcome['content'] ?? null)
                || (is_array($query) ? array_filter($query, fn (array $row): bool => $names($row['value'] ?? null)) !== [] : $names($query));
        };

        if ($outcomeNames($get('submit_notifications.default') ?? [])) {
            $usages[] = __('filament-form-builder::general.canvas.usage_after_submit');
        }

        foreach ($get('submit_notifications.rules') ?? [] as $rule) {
            if ($conditions($rule) || $outcomeNames($rule)) {
                $usages[] = __('filament-form-builder::general.canvas.usage_outcome');

                break;
            }
        }

        return $usages;
    }

    public static function renameKeyInNotifications(string $from, string $to, Get $get, Set $set): void
    {
        $outcome = function (array $outcome) use ($from, $to): array {
            $query = $outcome['query'] ?? null;

            return [
                ...$outcome,
                'content' => MergeTags::rename($outcome['content'] ?? null, $from, $to),
                'query' => is_array($query)
                    ? array_map(fn (array $row): array => [...$row, 'value' => MergeTags::rename($row['value'] ?? null, $from, $to)], $query)
                    : SubmissionPlaceholders::rename($query, $from, $to),
                'conditions' => array_map(
                    fn (array $rule): array => ($rule['key'] ?? null) === $from ? [...$rule, 'key' => $to] : $rule,
                    $outcome['conditions'] ?? [],
                ),
            ];
        };

        $set('submit_notifications.default', $outcome($get('submit_notifications.default') ?? []));
        $set('submit_notifications.rules', array_map($outcome, $get('submit_notifications.rules') ?? []));

        $field = fn (?string $recipient): ?string => $recipient === EmailNotification::FIELD_PREFIX . $from ? EmailNotification::FIELD_PREFIX . $to : $recipient;
        $notifications = [];

        foreach ($get('notifications') ?? [] as $id => $notification) {
            foreach (['subject', 'content', 'senderName'] as $text) {
                $notification[$text] = MergeTags::rename($notification[$text] ?? null, $from, $to);
            }

            foreach (['to', 'cc', 'bcc'] as $list) {
                $notification[$list] = array_map($field, $notification[$list] ?? []);
            }

            $notification['reply_to'] = $field($notification['reply_to'] ?? null);
            $notification['conditions'] = array_map(
                fn (array $rule): array => ($rule['key'] ?? null) === $from ? [...$rule, 'key' => $to] : $rule,
                $notification['conditions'] ?? [],
            );

            $notifications[$id] = $notification;
        }

        $set('notifications', $notifications);
    }

    public static function getSubmitNotificationSection(): Section
    {
        return Section::make(__('filament-form-builder::general.submit_notification'))
            ->description(__('filament-form-builder::general.submit_notification_explanation'))
            ->icon('heroicon-o-paper-airplane')
            ->schema([
                SubmitNotifications::make(),
            ]);
    }

    public static function getEmailNotificationSection(): Section
    {
        return Section::make(__('filament-form-builder::general.email_notifications'))
            ->description(__('filament-form-builder::general.email_notification_explanation'))
            ->visible(self::hasNotificationsEnabled(...))
            ->icon('heroicon-o-bell-alert')
            ->schema([
                NotificationList::make('notifications')
                    ->hiddenLabel(),
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
                    ->formatStateUsing(fn (?string $state): ?string => FormTypeHelper::options()[$state] ?? $state)
                    ->label(__('filament-form-builder::general.type')),
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
            ->recordUrl(static::recordUrl(...))
            ->filters([
                SelectFilter::make('template')
                    ->label(__('filament-form-builder::general.type'))
                    ->options(FormTypeHelper::options())
                    ->multiple()
                    ->searchable(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Only where there is nothing to edit: next to an edit button it
                // reads as "looking is all you may do".
                Actions\ViewAction::make()
                    ->visible(fn (FormModel $record): bool => ! static::canEdit($record)),
                Actions\EditAction::make(),
                Actions\ForceDeleteAction::make()
                    ->modalDescription(__('filament-form-builder::fields.form_force_deletion_warning')),
                Actions\RestoreAction::make(),
                Actions\ActionGroup::make([
                    Actions\ReplicateAction::make()
                        ->authorize(fn (?FormModel $record): bool => static::canCreate() && (!$record || static::canReplicate($record)))
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

    public static function viewUrl(FormModel $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    /**
     * Where a link to a form should land: the edit page for anyone who may
     * change it. The read-only page otherwise looks like a refusal.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function recordUrl(FormModel $record, array $parameters = []): string
    {
        return static::getUrl(static::canEdit($record) ? 'edit' : 'view', ['record' => $record, ...$parameters]);
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

    /**
     * The type picked in the form, which can differ from the stored one while
     * the editor is still choosing.
     */
    public static function getFormType(Get $get, ?FormModel $record = null): ?FormType
    {
        return FormTypeHelper::make($get('template'), $record);
    }

    /**
     * The kinds of outcome after a submission the selected form type allows.
     *
     * @return array<string, string>
     */
    public static function getSubmitNotificationTypes(Get $get): array
    {
        return SubmitNotifications::getTypes(static::getFormType($get));
    }

    public static function hasNotificationsEnabled(Get $get): bool
    {
        return static::getFormType($get)?->hasNotifications() ?? false;
    }

    public static function hasIntegrationsEnabled(Get $get): bool
    {
        return static::getFormType($get)?->hasIntegrations() ?? false;
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

    public static function hasSettings(Get $get, ?FormModel $record): bool
    {
        return filled(static::getFormType($get, $record)?->settings());
    }

    public static function hasCustomFields(Get $get): bool
    {
        return static::getFormType($get)?->hasCustomFields() ?? false;
    }
}
