<?php

namespace VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource\Pages;

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
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Filament\Resources\FormResource;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

class ViewFormSubmission extends ViewRecord
{
    protected static string $resource = FormSubmissionResource::class;

    public function getTitle(): string
    {
        return __('filament-form-builder::general.submission.title', ['id' => $this->getRecord()->getKey()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var FormSubmission $record */
        $record = $this->getRecord();
        $logs = $record->notificationLogs()->orderBy('created_at')->get();

        return $schema
            ->components([
                $this->getSummarySection($logs),
                Grid::make(['lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        $this->getAnswersSection($record)
                            ->columnSpan(['lg' => 2]),
                        Group::make([
                            $this->getFilesSection($record),
                            $this->getNotificationsSection($logs),
                            $this->getDetailsSection($record),
                            $this->getIntegrationsSection($record),
                        ]),
                    ]),
            ]);
    }

    /**
     * @param  Collection<int, FormSubmissionNotificationLog>  $logs
     */
    protected function getSummarySection(Collection $logs): Section
    {
        return Section::make()
            ->columnSpanFull()
            ->columns(['sm' => 2, 'lg' => 4])
            ->schema([
                TextEntry::make('id')
                    ->label(__('filament-form-builder::general.submission.id'))
                    ->icon(Heroicon::OutlinedHashtag)
                    ->weight('semibold'),
                TextEntry::make('form.title')
                    ->label(__('filament-form-builder::general.submission.form'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->url(fn (FormSubmission $record): ?string => $record->form ? FormResource::recordUrl($record->form) : null),
                TextEntry::make('created_at')
                    ->label(__('filament-form-builder::general.submission.submitted'))
                    ->icon(Heroicon::OutlinedCalendar)
                    ->dateTime('j F Y, H:i'),
                TextEntry::make('notifications')
                    ->label(__('filament-form-builder::general.notifications_label'))
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->state(__('filament-form-builder::general.submission.notifications_sent', [
                        'sent' => $logs->where('status', 'sent')->count(),
                        'total' => $logs->count(),
                    ]))
                    ->hidden($logs->isEmpty()),
            ]);
    }

    protected function getAnswersSection(FormSubmission $record): Section
    {
        ['current' => $current, 'removed' => $removed] = $record->getAnswers();

        return Section::make(__('filament-form-builder::general.submission.answers'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->afterHeader([
                Text::make(trans_choice('filament-form-builder::general.submission.answer_count', count($current), ['count' => count($current)]))
                    ->color('gray'),
            ])
            ->schema([
                View::make('filament-form-builder::filament.submission.answers')
                    ->viewData(['answers' => $current, 'submission' => $record]),
                Section::make(__('filament-form-builder::general.submission.removed'))
                    ->description(__('filament-form-builder::general.submission.removed_description'))
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->afterHeader([$this->getCount(count($removed))])
                    ->compact()
                    ->secondary()
                    ->hidden($removed === [])
                    ->schema([
                        View::make('filament-form-builder::filament.submission.answers')
                            ->viewData(['answers' => $removed, 'submission' => $record]),
                    ]),
            ]);
    }

    protected function getFilesSection(FormSubmission $record): Section
    {
        $files = array_merge(...array_values($record->getFiles()));

        return Section::make(__('filament-form-builder::general.files'))
            ->icon(Heroicon::OutlinedPaperClip)
            ->afterHeader([$this->getCount(count($files))])
            ->hidden($files === [])
            ->schema([
                View::make('filament-form-builder::filament.submission.files')
                    ->viewData(['files' => $files, 'submission' => $record]),
            ]);
    }

    /**
     * @param  Collection<int, FormSubmissionNotificationLog>  $logs
     */
    protected function getNotificationsSection(Collection $logs): Section
    {
        return Section::make(__('filament-form-builder::general.notifications_label'))
            ->icon(Heroicon::OutlinedEnvelope)
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
                TextEntry::make('form_type')
                    ->label(__('filament-form-builder::general.submission.form_type'))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->state($record->form?->getType()->getLabel())
                    ->placeholder('—')
                    ->inlineLabel(),
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
