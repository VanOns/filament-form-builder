<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Session;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Classes\SubmissionAnswer;
use VanOns\FilamentFormBuilder\Classes\SubmissionMeta;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

class ViewFormSubmission extends ViewRecord
{
    protected static string $resource = FormSubmissionResource::class;

    // Kept for the session, so the next submission opens the way the last one was read.
    #[Session]
    public string $answersLayout = 'form';

    public function getTitle(): string
    {
        return __('filament-form-builder::general.submission.title', ['id' => $this->getRecord()->getKey()]);
    }

    public function getSubheading(): ?string
    {
        /** @var FormSubmission $submission */
        $submission = $this->getRecord();

        return collect([$submission->form?->title, $submission->created_at?->translatedFormat('j F Y, H:i')])->filter()->implode(' · ');
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);

        /** @var FormSubmission $submission */
        $submission = $this->getRecord();

        if (!$submission->isRead()) {
            $submission->markAsRead();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getNeighbourAction('newer', Heroicon::ChevronUp, 'k'),
            $this->getNeighbourAction('older', Heroicon::ChevronDown, 'j'),
            Action::make('markUnread')
                ->label(__('filament-form-builder::general.submission.mark_unread'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->action(function (): void {
                    /** @var FormSubmission $submission */
                    $submission = $this->getRecord();
                    $submission->markAsRead(false);

                    // Back to the list it now stands out in again, as a mail program does.
                    $this->redirect(FormResource::getUrl('edit', ['record' => $submission->form_id, 'tab' => 'submissions']));
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Steps through the submissions of the same form in the order of its list,
     * the newest at the top, with the keys a mail program uses.
     */
    protected function getNeighbourAction(string $name, Heroicon $icon, string $key): Action
    {
        /** @var FormSubmission $submission */
        $submission = $this->getRecord();
        $isNewer = $name === 'newer';

        $neighbour = FormSubmission::query()
            ->where('form_id', $submission->form_id)
            ->where($submission->getKeyName(), $isNewer ? '>' : '<', $submission->getKey())
            ->orderBy($submission->getKeyName(), $isNewer ? 'asc' : 'desc')
            ->value($submission->getKeyName());

        return Action::make($name)
            ->label(__("filament-form-builder::general.submission.{$name}"))
            ->tooltip(__("filament-form-builder::general.submission.{$name}") . " ({$key})")
            ->icon($icon)
            ->iconButton()
            ->color('gray')
            ->url($neighbour === null ? null : FormSubmissionResource::getUrl('view', ['record' => $neighbour]))
            ->disabled($neighbour === null)
            ->keyBindings([$key]);
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var FormSubmission $record */
        $record = $this->getRecord();
        $logs = $record->notificationLogs()->orderBy('created_at')->get();
        ['current' => $current, 'removed' => $removed] = $record->getAnswers();

        return $schema
            ->components([
                Grid::make(['lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make([
                            $this->getAnswersSection($record, $current),
                            $this->getRemovedSection($record, $removed),
                        ])->columnSpan(['lg' => 2]),
                        Group::make([
                            $this->getNotificationsSection($logs),
                            $this->getDetailsSection($record),
                            $this->getIntegrationsSection($record),
                        ]),
                    ]),
            ]);
    }

    /**
     * @param  list<SubmissionAnswer>  $answers
     */
    protected function getAnswersSection(FormSubmission $record, array $answers): Section
    {
        $isList = fn (): bool => $this->answersLayout === 'list';

        return Section::make(__('filament-form-builder::general.submission.answers'))
            ->key('answers')
            ->icon(Heroicon::OutlinedDocumentText)
            ->afterHeader([
                Text::make(trans_choice('filament-form-builder::general.submission.answer_count', count($answers), ['count' => count($answers)]))
                    ->color('gray'),
                Action::make('answersLayout')
                    ->label(fn (): string => $isList()
                        ? __('filament-form-builder::general.submission.as_form')
                        : __('filament-form-builder::general.submission.as_list'))
                    ->icon(fn (): Heroicon => $isList() ? Heroicon::OutlinedSquares2x2 : Heroicon::OutlinedListBullet)
                    ->color('gray')
                    ->size(Size::Small)
                    ->action(function (): void {
                        $this->answersLayout = $this->answersLayout === 'list' ? 'form' : 'list';
                    }),
            ])
            ->schema([
                View::make('filament-form-builder::filament.submission.answer-groups')
                    ->viewData(['groups' => $record->getAnswerGroups(), 'submission' => $record])
                    ->hidden($isList),
                View::make('filament-form-builder::filament.submission.answers')
                    ->viewData(['answers' => $answers, 'submission' => $record, 'showFilesHint' => true])
                    ->visible($isList),
            ]);
    }

    /**
     * @param  list<SubmissionAnswer>  $answers
     */
    protected function getRemovedSection(FormSubmission $record, array $answers): Section
    {
        return Section::make(__('filament-form-builder::general.submission.removed'))
            ->description(trans_choice('filament-form-builder::general.submission.removed_description', count($answers), ['count' => count($answers)]))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->collapsible()
            ->collapsed()
            ->compact()
            ->secondary()
            ->hidden($answers === [])
            ->schema([
                View::make('filament-form-builder::filament.submission.answers')
                    ->viewData(['answers' => $answers, 'submission' => $record]),
            ]);
    }

    /**
     * @param  Collection<int, FormSubmissionNotificationLog>  $logs
     */
    protected function getNotificationsSection(Collection $logs): Section
    {
        return Section::make(__('filament-form-builder::general.notifications_label'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->description($logs->isEmpty() ? null : __('filament-form-builder::general.submission.notifications_sent', [
                'sent' => $logs->where('status', 'sent')->count(),
                'total' => $logs->count(),
            ]))
            ->afterHeader([$this->getCount($logs->count())])
            ->hidden($logs->isEmpty() && !config('filament-form-builder.email_notifications'))
            ->schema([
                View::make('filament-form-builder::filament.submission.notifications')
                    ->viewData(['logs' => $logs]),
            ]);
    }

    protected function getDetailsSection(FormSubmission $record): Section
    {
        return Section::make(__('filament-form-builder::general.submission.details'))
            ->icon(Heroicon::OutlinedInformationCircle)
            ->schema([
                TextEntry::make('created_at')
                    ->label(__('filament-form-builder::general.submission.submitted_at'))
                    ->icon(Heroicon::OutlinedClock)
                    ->dateTime('j M Y, H:i:s')
                    ->inlineLabel(),
                TextEntry::make('source_url')
                    ->label(__('filament-form-builder::general.submission.submitted_from'))
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->url(fn (?string $state): ?string => $state)
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->limit(60)
                    ->placeholder('—')
                    ->inlineLabel(),
                TextEntry::make('form_type')
                    ->label(__('filament-form-builder::general.submission.form_type'))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->state($record->form?->getType()->getLabel())
                    ->placeholder('—')
                    ->inlineLabel(),
                TextEntry::make('browser')
                    ->label(__('filament-form-builder::general.submission.browser'))
                    ->icon(Heroicon::OutlinedComputerDesktop)
                    ->state(SubmissionMeta::describe($record->meta, 'user_agent'))
                    ->tooltip($record->meta['user_agent'] ?? null)
                    ->hidden(!isset($record->meta['user_agent']))
                    ->inlineLabel(),
                TextEntry::make('locale')
                    ->label(__('filament-form-builder::general.submission.locale'))
                    ->icon(Heroicon::OutlinedLanguage)
                    ->state(SubmissionMeta::describe($record->meta, 'locale'))
                    ->hidden(!isset($record->meta['locale']))
                    ->inlineLabel(),
                TextEntry::make('submitted_by')
                    ->label(__('filament-form-builder::general.submission.submitted_by'))
                    ->icon(Heroicon::OutlinedUser)
                    ->state(SubmissionMeta::describe($record->meta, 'user'))
                    ->hidden(!isset($record->meta['user']))
                    ->inlineLabel(),
                TextEntry::make('campaign')
                    ->label(__('filament-form-builder::general.submission.campaign'))
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->state(SubmissionMeta::describe($record->meta, 'campaign'))
                    ->hidden(!isset($record->meta['campaign']))
                    ->inlineLabel(),
                TextEntry::make('ip')
                    ->label(__('filament-form-builder::general.submission.ip'))
                    ->icon(Heroicon::OutlinedSignal)
                    ->state(SubmissionMeta::describe($record->meta, 'ip'))
                    ->hidden(!isset($record->meta['ip']))
                    ->inlineLabel(),
                ...array_map(function (SubmissionAnswer $answer): TextEntry {
                    $text = FormSubmission::toText($answer->value);
                    $url = filter_var($text, FILTER_VALIDATE_URL) !== false ? $text : null;

                    return TextEntry::make("type_value_{$answer->key}")
                        ->label($answer->label)
                        ->icon($answer->icon)
                        ->state($text)
                        ->url($url)
                        ->openUrlInNewTab()
                        ->color($url !== null ? 'primary' : null)
                        ->limit($url !== null ? 60 : null)
                        ->inlineLabel();
                }, $record->getTypeAnswers()),
            ]);
    }

    public function getIntegrationsSection(FormSubmission $record): Section
    {
        $integrationResponses = $record->integrations ?? [];

        if (empty($integrationResponses)) {
            return Section::make(__('filament-form-builder::general.integration_responses'))
                ->hidden(empty(Integration::getIntegrations()))
                ->icon(Heroicon::OutlinedServerStack)
                ->description(__('filament-form-builder::general.no_integrations'));
        }

        $entries = [];

        foreach ($integrationResponses as $index => $integrationData) {
            $integrationClass = $integrationData['integration'] ?? 'Unknown';

            $label = class_exists($integrationClass) && is_subclass_of($integrationClass, Integration::class)
                ? $integrationClass::label()
                : class_basename($integrationClass);

            $success = $integrationData['response']['success'] ?? null;

            $color = match ($success) {
                true => 'success',
                false => 'danger',
                default => 'gray',
            };

            $entries[] = Section::make($label)
                ->description($integrationClass)
                ->collapsed()
                ->compact()
                ->icon(match ($success) {
                    true => Heroicon::OutlinedCheckCircle,
                    false => Heroicon::OutlinedXCircle,
                    default => Heroicon::OutlinedQuestionMarkCircle,
                })
                ->iconColor($color)
                ->schema([
                    TextEntry::make("integrations.{$index}.response.success")
                        ->label(__('filament-form-builder::general.status'))
                        ->badge()
                        ->color($color)
                        ->formatStateUsing(fn ($state) => match ($state) {
                            true => __('filament-form-builder::general.success'),
                            false => __('filament-form-builder::general.failed'),
                            default => __('filament-form-builder::general.unknown'),
                        }),
                    TextEntry::make("integrations.{$index}.ran_at")
                        ->label(__('filament-form-builder::general.submission.ran_at'))
                        ->icon(Heroicon::OutlinedClock)
                        ->dateTime('j M Y, H:i:s')
                        ->placeholder('—'),
                    KeyValueEntry::make("integrations.{$index}.response.response")
                        ->label(__('filament-form-builder::general.response'))
                        ->keyLabel(__('filament-form-builder::general.key'))
                        ->valueLabel(__('filament-form-builder::general.value')),
                ])
                ->collapsible();
        }

        return Section::make(__('filament-form-builder::general.integration_responses'))
            ->icon(Heroicon::OutlinedServerStack)
            ->description(__('filament-form-builder::general.integration_responses_description'))
            ->afterHeader([$this->getCount(count($entries))])
            ->collapsible()
            ->collapsed()
            ->schema($entries);
    }

    protected function getCount(int $count): Text
    {
        return Text::make((string) $count)->color('gray');
    }
}
