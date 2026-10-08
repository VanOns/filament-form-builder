<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Session;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Classes\SubmissionAnswer;
use VanOns\FilamentFormBuilder\Classes\SubmissionMeta;
use VanOns\FilamentFormBuilder\Enums\IntegrationStatus;
use VanOns\FilamentFormBuilder\Enums\NotificationStatus;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionIntegrationLog;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

class ViewFormSubmission extends ViewRecord
{
    protected static string $resource = FormSubmissionResource::class;

    // Kept for the session, so the next submission opens the way the last one was read.
    #[Session]
    public string $answersLayout = 'form';

    /**
     * @var array<string, array{label: string, summary: ?string, status: IntegrationStatus, error: ?string, ranAt: ?Carbon, attempts: int, response: array<mixed>, log: ?int}>|null
     */
    protected ?array $integrationRows = null;

    public function getTitle(): string
    {
        return __('filament-form-builder::general.submission.title', ['id' => $this->getRecord()->getKey()]);
    }

    public function getSubheading(): Htmlable
    {
        /** @var FormSubmission $submission */
        $submission = $this->getRecord();
        $form = $submission->form;

        $title = match (true) {
            $form === null => null,
            // Filament's link stops at text-sm, the subheading is larger.
            FormResource::canView($form) => '<a href="' . e(FormResource::recordUrl($form)) . '" class="ffb-subheading-link">' . e($form->title) . '</a>',
            default => e($form->title),
        };

        return new HtmlString(collect([$title, e((string) $submission->created_at?->translatedFormat('j F Y, H:i'))])->filter()->implode(' · '));
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
                            $this->getIntegrationsSection($record),
                            $this->getDetailsSection($record),
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
                    ->viewData(fn (): array => ['groups' => $record->getAnswerGroups(), 'submission' => $record])
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
                'sent' => $logs->where('status', NotificationStatus::Sent)->count(),
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
                ...array_map(fn (string $key, string $label): TextEntry => TextEntry::make("meta_{$key}")
                    ->label($label)
                    ->icon(match ($key) {
                        'user_agent' => Heroicon::OutlinedComputerDesktop,
                        'locale' => Heroicon::OutlinedLanguage,
                        'user' => Heroicon::OutlinedUser,
                        'campaign' => Heroicon::OutlinedMegaphone,
                        default => Heroicon::OutlinedSignal,
                    })
                    ->state(SubmissionMeta::describe($record->meta, $key))
                    ->tooltip($key === 'user_agent' ? $record->meta[$key] ?? null : null)
                    ->hidden(!isset($record->meta[$key]))
                    ->inlineLabel(), array_keys(SubmissionMeta::allLabels()), SubmissionMeta::allLabels()),
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

    protected function getIntegrationsSection(FormSubmission $record): Section
    {
        $counted = fn (): array => array_filter($this->getIntegrationRows($record), fn (array $row): bool => $row['status'] !== IntegrationStatus::Skipped);

        return Section::make(__('filament-form-builder::general.integrations.label'))
            ->icon(Heroicon::OutlinedServerStack)
            ->description(fn (): ?string => $counted() === [] ? null : __('filament-form-builder::general.integrations.runs_summary', [
                'succeeded' => count(array_filter($counted(), fn (array $row): bool => $row['status'] === IntegrationStatus::Succeeded)),
                'total' => count($counted()),
            ]))
            ->afterHeader([Text::make(fn (): string => (string) count($this->getIntegrationRows($record)))->color('gray')])
            ->hidden(fn (): bool => $this->getIntegrationRows($record) === [] && Integration::getIntegrations() === [])
            ->schema([
                View::make('filament-form-builder::filament.submission.integrations')
                    ->key('integration-runs')
                    ->viewData(fn (): array => ['rows' => $this->getIntegrationRows($record)])
                    ->registerActions([
                        $this->getRerunIntegrationAction($record),
                        $this->getIntegrationResponseAction($record),
                    ]),
            ]);
    }

    /**
     * Each integration that ran for the submission, or that its conditions
     * left out. A submission from before v3 shows what was kept back then.
     *
     * @return array<string, array{label: string, summary: ?string, status: IntegrationStatus, error: ?string, ranAt: ?Carbon, attempts: int, response: array<mixed>, log: ?int}>
     */
    protected function getIntegrationRows(FormSubmission $record): array
    {
        return $this->integrationRows ??= $this->findIntegrationRows($record);
    }

    /**
     * @return array<string, array{label: string, summary: ?string, status: IntegrationStatus, error: ?string, ranAt: ?Carbon, attempts: int, response: array<mixed>, log: ?int}>
     */
    protected function findIntegrationRows(FormSubmission $record): array
    {
        $integrations = $record->form?->getIntegrations() ?? [];
        $rows = [];

        foreach ($record->integrationLogs()->orderBy('id')->get() as $log) {
            $class = Integration::resolve($log->integration);
            $settings = $integrations[$log->integration_id] ?? null;

            $rows["log-{$log->id}"] = [
                'label' => $class === null ? class_basename($log->integration) : $class::label(),
                'summary' => $class === null || $settings === null ? null : $class::summary($settings),
                'status' => $log->status,
                'error' => $log->status === IntegrationStatus::Skipped ? __('filament-form-builder::general.integrations.skipped_reason') : $log->error,
                'ranAt' => $log->ran_at,
                'attempts' => $log->attempts,
                'response' => $log->response ?? [],
                'log' => $log->id,
            ];
        }

        if ($rows !== []) {
            return $rows;
        }

        foreach ($record->integrations ?? [] as $index => $result) {
            $class = Integration::resolve($result['integration'] ?? null);
            $success = $result['response']['success'] ?? null;

            $rows["legacy-{$index}"] = [
                'label' => $class === null ? class_basename((string) ($result['integration'] ?? '')) : $class::label(),
                'summary' => null,
                'status' => $success === false ? IntegrationStatus::Failed : IntegrationStatus::Succeeded,
                'error' => null,
                'ranAt' => filled($result['ran_at'] ?? null) ? Carbon::parse($result['ran_at']) : null,
                'attempts' => 1,
                'response' => is_array($result['response']['response'] ?? null) ? $result['response']['response'] : [],
                'log' => null,
            ];
        }

        return $rows;
    }

    protected function getRerunIntegrationAction(FormSubmission $record): Action
    {
        $row = fn (array $arguments): ?array => $this->getIntegrationRows($record)[$arguments['row'] ?? ''] ?? null;
        $label = fn (array $arguments): string => $row($arguments)['label'] ?? '';

        return Action::make('rerunIntegration')
            ->label(__('filament-form-builder::general.integrations.rerun'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->link()
            ->size(Size::Small)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalHeading(fn (array $arguments): string => __('filament-form-builder::general.integrations.rerun_heading', ['label' => $label($arguments)]))
            ->modalDescription(fn (array $arguments): string => __('filament-form-builder::general.integrations.rerun_description', ['label' => $label($arguments)]))
            ->modalSubmitActionLabel(__('filament-form-builder::general.integrations.rerun'))
            ->visible(fn (): bool => FormSubmissionResource::canEdit($this->getRecord()))
            ->action(function (array $arguments) use ($record, $row, $label): void {
                $log = $record->integrationLogs()->find($row($arguments)['log'] ?? null);

                if (!$log instanceof FormSubmissionIntegrationLog || $log->status === IntegrationStatus::Queued) {
                    return;
                }

                $log->update(['status' => IntegrationStatus::Queued, 'response' => null, 'error' => null, 'attempts' => 0, 'ran_at' => null]);
                $record->form?->getType()->runIntegration($log->id);
                $log->refresh();
                $this->integrationRows = null;

                Notification::make()
                    ->status(match ($log->status) {
                        IntegrationStatus::Succeeded => 'success',
                        IntegrationStatus::Failed => 'danger',
                        default => 'info',
                    })
                    ->title($log->status === IntegrationStatus::Queued
                        ? __('filament-form-builder::general.integrations.rerun_queued', ['label' => $label($arguments)])
                        : $label($arguments) . ': ' . $log->status->getLabel())
                    ->body($log->error)
                    ->send();
            });
    }

    protected function getIntegrationResponseAction(FormSubmission $record): Action
    {
        $row = fn (array $arguments): ?array => $this->getIntegrationRows($record)[$arguments['row'] ?? ''] ?? null;

        return Action::make('integrationResponse')
            ->label(__('filament-form-builder::general.integrations.response'))
            ->icon(Heroicon::OutlinedEye)
            ->link()
            ->color('gray')
            ->size(Size::Small)
            ->modalHeading(fn (array $arguments): string => __('filament-form-builder::general.integrations.response_heading', ['label' => $row($arguments)['label'] ?? '']))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-form-builder::general.integrations.close'))
            ->schema(fn (array $arguments): array => [
                KeyValueEntry::make('response')
                    ->hiddenLabel()
                    ->state(array_map(
                        fn (mixed $value): string => match (true) {
                            is_bool($value) => $value ? 'true' : 'false',
                            is_scalar($value) => (string) $value,
                            $value === null => '',
                            default => (string) json_encode($value),
                        },
                        Arr::dot($row($arguments)['response'] ?? []),
                    ))
                    ->placeholder(__('filament-form-builder::general.integrations.no_response')),
            ]);
    }

    protected function getCount(int $count): Text
    {
        return Text::make((string) $count)->color('gray');
    }
}
