<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;
use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Classes\SubmitNotification;
use VanOns\FilamentFormBuilder\Enums\SubmitNotificationType;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;
use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Helpers\FormTypeHelper;
use VanOns\FilamentFormBuilder\Models\Form;

/**
 * What happens after a submission, stored as one list like the notifications.
 * The outcome every form has is the last item and is edited in place; the
 * outcomes for certain answers come before it and are edited as cards.
 */
class SubmitNotifications extends Group
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->statePath('submit_notifications');

        $this->schema([
            Group::make(static::getOutcomeSchema())->statePath('default'),
            SubmitNotificationList::make('rules')
                ->label(__('filament-form-builder::general.submit_rules.label'))
                ->hiddenLabel(),
        ]);

        $this->mutateDehydratedStateUsing(static fn (?array $state): array => [
            ...array_map(SubmitNotification::normalize(...), array_values($state['rules'] ?? [])),
            [...SubmitNotification::normalize($state['default'] ?? []), 'conditions' => []],
        ]);
    }

    /**
     * The stored list becomes the outcome in place and the cards before the
     * fields inside build their state from it.
     *
     * @param  array<string, mixed>|null  $hydratedDefaultState
     * @param  array<string, true>  $appliedStateCastPaths
     */
    public function hydrateState(?array &$hydratedDefaultState, bool $shouldCallHydrationHooks = true, bool $shouldApplyStateCasts = true, array &$appliedStateCastPaths = []): void
    {
        $rawState = $this->getRawState();

        if (is_array($rawState) && array_is_list($rawState)) {
            $outcomes = array_map(SubmitNotification::normalize(...), array_filter($rawState, is_array(...)));
            $default = array_pop($outcomes) ?? SubmitNotification::normalize([]);

            $this->rawState([
                'default' => $default,
                'rules' => array_column($outcomes, null, 'id'),
            ]);
        }

        parent::hydrateState($hydratedDefaultState, $shouldCallHydrationHooks, $shouldApplyStateCasts, $appliedStateCastPaths);
    }

    /**
     * One outcome: a message or a page to send the visitor to.
     *
     * @return array<Component>
     */
    public static function getOutcomeSchema(): array
    {
        $isType = fn (SubmitNotificationType $type): Closure => fn (Get $get, Livewire $livewire): bool => static::getType($get, $livewire) === $type->value;

        return [
            ChoiceCards::make('type')
                ->required()
                ->label(__('filament-form-builder::general.what_happens_after_submission'))
                ->hiddenLabel()
                ->options(fn (Livewire $livewire): array => static::getTypes(static::getFormType($livewire)))
                ->descriptions(fn (Livewire $livewire): array => array_map(
                    fn (string $type): string => SubmitNotificationType::from($type)->getDescription(),
                    array_combine($types = array_keys(static::getTypes(static::getFormType($livewire))), $types),
                ))
                ->icons([
                    SubmitNotificationType::Content->value => Heroicon::OutlinedChatBubbleLeftEllipsis,
                    SubmitNotificationType::URL->value => Heroicon::OutlinedArrowTopRightOnSquare,
                ])
                ->default(fn (Livewire $livewire): ?string => array_key_first(static::getTypes(static::getFormType($livewire))))
                ->live()
                ->visible(fn (Livewire $livewire): bool => count(static::getTypes(static::getFormType($livewire))) > 1)
                ->dehydratedWhenHidden(),
            MergeTagEditor::make('content', withSubmissionLink: false)
                ->label(__('filament-form-builder::general.submit_notification_message'))
                ->required()
                ->default(fn (): string => '<p>' . e(__('filament-form-builder::general.form_submitted_successfully')) . '</p>')
                ->placeholder(__('filament-form-builder::general.form_submitted_successfully'))
                ->visible($isType(SubmitNotificationType::Content)),
            Group::make(FilamentFormBuilderPlugin::getRedirectSchema())
                ->visible($isType(SubmitNotificationType::URL)),
            Group::make(fn (Get $get): array => static::getQuerySchema($get))
                ->visible(fn (Get $get, Livewire $livewire): bool => $isType(SubmitNotificationType::URL)($get, $livewire)
                    && (static::getFormType($livewire)?->hasSubmitNotificationQuery() ?? config('filament-form-builder.redirect_query', true) === true)),
        ];
    }

    /**
     * The kinds of outcome a form type allows, the message first.
     *
     * @return array<string, string>
     */
    public static function getTypes(?FormType $formType): array
    {
        $types = [];

        foreach ([SubmitNotificationType::Content, SubmitNotificationType::URL] as $type) {
            $isAllowed = $formType === null || match ($type) {
                SubmitNotificationType::URL => $formType->hasRedirect(),
                SubmitNotificationType::Content => $formType->hasNotificationMessage(),
            };

            if ($isAllowed) {
                $types[$type->value] = $type->getLabel();
            }
        }

        return $types;
    }

    /**
     * The kind an outcome has, or the only one the form type allows.
     */
    public static function getType(Get $get, Livewire $livewire): ?string
    {
        $types = array_keys(static::getTypes(static::getFormType($livewire)));
        $type = $get('type');

        return count($types) === 1 ? $types[0] : ($type instanceof SubmitNotificationType ? $type->value : $type);
    }

    /**
     * The type chosen on the page, also from inside a slide-over.
     */
    public static function getFormType(Livewire $livewire): ?FormType
    {
        $record = data_get($livewire, 'record');

        return FormTypeHelper::make(data_get($livewire, 'data.template'), $record instanceof Form ? $record : null);
    }

    /**
     * One row per parameter, behind a link until there are any; a query
     * string with a part that is no parameter stays text.
     *
     * @return array<Component>
     */
    public static function getQuerySchema(Get $get): array
    {
        $query = $get('query');

        if (is_string($query) && QueryParameters::parse($query) === null) {
            return [
                TextInput::make('query')
                    ->label(__('filament-form-builder::general.submit_notification_query'))
                    ->helperText(__('filament-form-builder::general.submit_notification_query_explanation'))
                    ->placeholder('name={{ $name }}&form={{ $form_title }}')
                    ->live(onBlur: true),
                TextEntry::make('placeholders')
                    ->hiddenLabel()
                    ->badge()
                    ->copyable()
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder(__('filament-form-builder::general.unknown'))
                    ->state(fn (Livewire $livewire): array => MergeTagEditor::form($livewire)->getPlaceholderList()),
                static::getRedirectExample(),
            ];
        }

        $isShown = fn (Get $get): bool => (bool) $get('show_query');

        return [
            Hidden::make('show_query')
                ->formatStateUsing(fn (Get $get): bool => filled($get('query')))
                ->dehydrated(false),
            Actions::make([
                Action::make('addQueryParameters')
                    ->label(__('filament-form-builder::general.submit_notification_query'))
                    ->icon(Heroicon::Plus)
                    ->link()
                    ->action(function (Set $set): void {
                        $set('query', [(string) Str::uuid() => ['name' => null, 'value' => null]]);
                        $set('show_query', true);
                    }),
            ])
                ->key('query_link')
                ->visible(fn (Get $get): bool => !$isShown($get)),
            QueryParameters::make('query')
                ->label(__('filament-form-builder::general.submit_notification_query'))
                ->helperText(__('filament-form-builder::general.submit_notification_query_helper'))
                ->visible($isShown),
            static::getRedirectExample(),
        ];
    }

    /**
     * Where the latest submission would have sent its visitor, so the rows
     * read as the URL they make.
     */
    public static function getRedirectExample(): Html
    {
        $example = function (Get $get, ?Form $record): ?HtmlString {
            $submission = $record?->submissions()->latest('id')->first();
            $url = $record === null ? null : FilamentFormBuilderPlugin::resolveRedirectUrl($get('url'), $record);
            $query = $get('query');

            if ($submission === null || blank($url)) {
                return null;
            }

            $filled = (string) SubmissionPlaceholders::make($submission)->appendQuery($url, is_array($query) ? QueryParameters::build($query) : $query);

            if ($filled === $url) {
                return null;
            }

            $address = str_starts_with($filled, $url)
                ? e($url) . '<span class="ffb-redirect-example-query">' . e(substr($filled, strlen($url))) . '</span>'
                : e($filled);

            return new HtmlString('<div class="ffb-redirect-example"><span class="ffb-redirect-example-title">'
                . e(__('filament-form-builder::general.redirect_example', ['number' => $submission->getKey()]))
                . '</span><code class="ffb-redirect-example-url">' . $address . '</code></div>');
        };

        return Html::make($example)
            ->visible(fn (Get $get, ?Form $record): bool => $example($get, $record) !== null);
    }
}
