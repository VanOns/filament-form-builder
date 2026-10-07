<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;
use stdClass;
use Throwable;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Classes\MergeTags;
use VanOns\FilamentFormBuilder\Classes\SubmissionFile;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FileUploadField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Mail\FormSubmission\FormSubmissionCreatedMail;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionNotificationLog;

/**
 * The e-mail notifications of a form as cards, each edited in a slide-over.
 */
class NotificationList extends Field
{
    protected string $view = 'filament-form-builder::filament.notification-list';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        // Stored as a list; while the form is open, by id, so an action can name one.
        $this->afterStateHydrated(static function (NotificationList $component, ?array $rawState): void {
            $items = [];

            foreach ($rawState ?? [] as $item) {
                if (is_array($item)) {
                    $item = $component->prepare($item);
                    $items[$item['id']] = $item;
                }
            }

            $component->rawState($items);
        });

        $this->mutateDehydratedStateUsing(static fn (?array $state): array => array_values($state ?? []));

        $this->registerActions([
            fn (NotificationList $component): Action => $component->getFormAction('add'),
            fn (NotificationList $component): Action => $component->getFormAction('edit'),
            fn (NotificationList $component): Action => $component->getCloneAction(),
            fn (NotificationList $component): Action => $component->getDeleteAction(),
            fn (NotificationList $component): Action => $component->getToggleAction(),
        ]);
    }

    /**
     * A notification as the editor holds it: in the current shape, with an id,
     * and with the placeholders of before turned into merge tags.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function prepare(array $item): array
    {
        $item = EmailNotification::normalize($item);
        $item['id'] ??= (string) Str::uuid();

        foreach (['subject', 'content', 'senderName'] as $field) {
            $item[$field] = MergeTags::fromLegacy($item[$field]);
        }

        return $item;
    }

    public function getFormAction(string $name): Action
    {
        return Action::make($name)
            ->modalHeading(__("filament-form-builder::general.notifications.{$name}_heading"))
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->slideOver()
            ->modalSubmitActionLabel(__('filament-form-builder::general.save'))
            ->fillForm(fn (array $arguments, NotificationList $component, Livewire $livewire): array => $component->getFormData($arguments, $livewire))
            ->schema(fn (NotificationList $component, Livewire $livewire): array => $component->getNotificationSchema($livewire))
            ->extraModalFooterActions(fn (Action $action, NotificationList $component): array => [
                $action->makeModalSubmitAction('sendTest', arguments: ['test' => true])
                    ->label(__('filament-form-builder::general.notifications.send_test'))
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('gray')
                    ->disabled($component->getLatestSubmission() === null)
                    ->tooltip($component->getLatestSubmission() === null ? __('filament-form-builder::general.notifications.send_test_unavailable') : null),
            ])
            ->action(function (array $arguments, array $data, NotificationList $component, Action $action): void {
                if ($arguments['test'] ?? false) {
                    $component->sendTest($data);
                    $action->halt();
                }

                $component->saveItem(is_string($arguments['item'] ?? null) ? $arguments['item'] : null, $data);
            });
    }

    public function getCloneAction(): Action
    {
        return Action::make('clone')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedDocumentDuplicate)
            ->modalHeading(__('filament-form-builder::general.notifications.clone_heading'))
            ->modalDescription(__('filament-form-builder::general.notifications.clone_description'))
            ->modalSubmitActionLabel(__('filament-forms::components.builder.actions.clone.label'))
            ->action(function (array $arguments, NotificationList $component): void {
                $items = $component->getRawState() ?? [];
                $item = $items[$arguments['item'] ?? ''] ?? null;

                if ($item !== null) {
                    $id = (string) Str::uuid();
                    $component->rawState([...$items, $id => [...$item, 'id' => $id]]);
                }
            });
    }

    public function getDeleteAction(): Action
    {
        return Action::make('delete')
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading(__('filament-form-builder::general.notifications.delete_heading'))
            ->modalDescription(__('filament-form-builder::general.notifications.delete_description'))
            ->modalSubmitActionLabel(__('filament-forms::components.builder.actions.delete.label'))
            ->action(function (array $arguments, NotificationList $component): void {
                $items = $component->getRawState() ?? [];
                unset($items[$arguments['item'] ?? '']);

                $component->rawState($items);
            });
    }

    public function getToggleAction(): Action
    {
        return Action::make('toggle')
            ->action(function (array $arguments, NotificationList $component): void {
                $items = $component->getRawState() ?? [];
                $id = (string) ($arguments['item'] ?? '');

                if (isset($items[$id])) {
                    $items[$id]['enabled'] = !($items[$id]['enabled'] ?? true);
                    $component->rawState($items);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function getFormData(array $arguments, Livewire $livewire): array
    {
        $item = ($this->getRawState() ?? [])[$arguments['item'] ?? ''] ?? $this->getPreset((string) ($arguments['preset'] ?? ''), $livewire);

        return [...$item, 'when' => filled($item['conditions'] ?? []) ? 'conditions' : 'always'];
    }

    /**
     * Where a new notification starts: empty, a copy for the person who sent
     * the form, or a message for whoever handles it.
     *
     * @return array<string, mixed>
     */
    public function getPreset(string $preset, Livewire $livewire): array
    {
        $respondent = array_key_first(MergeTagEditor::form($livewire)->getEmailRecipients());
        $tag = fn (string $id): string => '<span data-type="mergeTag" data-id="' . e($id) . '"></span>';
        $text = fn (string $key): string => e(__("filament-form-builder::general.notifications.presets.{$key}"));

        return match ($preset) {
            'confirmation' => [
                'to' => $respondent === null ? [] : [$respondent],
                'subject' => '<p>' . $text('confirmation_subject') . '</p>',
                'content' => '<p>' . $text('confirmation_body') . '</p><p>' . $tag('all_fields') . '</p>',
            ],
            'staff' => [
                'reply_to' => $respondent,
                'subject' => '<p>' . $text('staff_subject') . ' ' . $tag('form_title') . '</p>',
                'content' => '<p>' . $text('staff_body') . '</p><p>' . $tag('all_fields') . '</p><p>' . $tag('submission_url') . '</p>',
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveItem(?string $id, array $data): void
    {
        if (($data['when'] ?? 'always') !== 'conditions') {
            $data['conditions'] = [];
        }

        unset($data['when']);

        $items = $this->getRawState() ?? [];
        $id ??= (string) Str::uuid();
        $items[$id] = $this->prepare([...$items[$id] ?? [], ...$data, 'id' => $id]);

        $this->rawState($items);
    }

    /**
     * @return array<Component>
     */
    public function getNotificationSchema(Livewire $livewire): array
    {
        $form = MergeTagEditor::form($livewire);

        return [
            Section::make(__('filament-form-builder::general.notifications.recipients'))
                ->compact()
                ->schema([
                    RecipientsInput::make('to')
                        ->label(__('filament-form-builder::general.notifications.to'))
                        ->helperText(__('filament-form-builder::general.notifications.to_helper'))
                        ->form($form)
                        ->required()
                        ->validationMessages(['required' => __('filament-form-builder::general.notifications.to_required')]),
                    RecipientsInput::make('reply_to')
                        ->label(__('filament-form-builder::general.notifications.reply_to'))
                        ->helperText(__('filament-form-builder::general.notifications.reply_to_helper'))
                        ->form($form)
                        ->multiple(false),
                ]),
            Section::make(__('filament-form-builder::general.notifications.message'))
                ->compact()
                ->schema([
                    MergeTagEditor::line('subject')
                        ->label(__('filament-form-builder::general.notifications.subject'))
                        ->required(),
                    MergeTagEditor::make('content')
                        ->label(__('filament-form-builder::general.notifications.content'))
                        ->required(),
                ]),
            Section::make(__('filament-form-builder::general.notifications.when'))
                ->compact()
                ->schema([
                    ToggleButtons::make('when')
                        ->hiddenLabel()
                        ->options([
                            'always' => __('filament-form-builder::general.notifications.when_always'),
                            'conditions' => __('filament-form-builder::general.notifications.when_conditions'),
                        ])
                        ->default('always')
                        ->grouped()
                        ->live(),
                    Group::make((new ConditionsEditor($this->getConditionFields($form), 'filament-form-builder::general.notifications.conditions_summary'))->schema())
                        ->visible(fn (Get $get): bool => $get('when') === 'conditions'),
                ]),
            Section::make(__('filament-form-builder::general.notifications.attachments'))
                ->compact()
                ->visible($this->hasUploads($form))
                ->schema([
                    Toggle::make('attach_files')
                        ->label(__('filament-form-builder::general.notifications.attach_files'))
                        ->helperText(__('filament-form-builder::general.notifications.attach_files_helper', [
                            'size' => Number::fileSize((int) config('filament-form-builder.form-uploads-attach-max-size', 10240) * 1024),
                        ])),
                ]),
            Section::make(__('filament-form-builder::general.notifications.sender'))
                ->compact()
                ->collapsible()
                ->collapsed(fn (Get $get): bool => blank($get('sender')))
                ->schema([
                    Callout::make(__('filament-form-builder::general.notifications.sender_callout'))
                        ->warning()
                        ->visible(fn (Get $get): bool => filled($get('sender'))),
                    TextInput::make('sender')
                        ->label(__('filament-form-builder::general.notifications.email'))
                        ->helperText(__('filament-form-builder::general.notifications.sender_hint'))
                        ->placeholder(config('mail.from.address'))
                        ->email()
                        ->live(onBlur: true),
                    MergeTagEditor::line('senderName')
                        ->label(__('filament-form-builder::general.notifications.name'))
                        ->helperText(__('filament-form-builder::general.notifications.name_hint'))
                        ->placeholder(config('mail.from.name')),
                ]),
        ];
    }

    /**
     * Sends the notification as it stands in the slide-over to the person
     * editing it, filled in with the latest submission, whatever its switch
     * and conditions say.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendTest(array $data): void
    {
        $submission = $this->getLatestSubmission();
        $user = Filament::auth()->user();
        $address = $user instanceof Model ? $user->getAttribute('email') : null;

        if ($submission === null || !is_string($address)) {
            return;
        }

        unset($data['when']);
        $notification = new EmailNotification($submission, $data);

        try {
            Mail::to($address)->send(new FormSubmissionCreatedMail(
                emailSubject: $notification->subject,
                emailContent: $notification->content,
                formSubmission: $submission,
                sender: $notification->sender,
                senderName: $notification->senderName,
                replyToAddress: $notification->replyTo,
                files: array_map(fn (SubmissionFile $file): array => ['path' => $file->path, 'name' => $file->name], $notification->attachments),
            ));

            Notification::make()
                ->success()
                ->title(__('filament-form-builder::general.notifications.test_sent', ['email' => $address]))
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(__('filament-form-builder::general.notifications.test_failed'))
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function getLatestSubmission(): ?FormSubmission
    {
        $record = $this->getRecord();

        return $record instanceof Form ? $record->submissions()->latest('id')->first() : null;
    }

    /**
     * What each notification sent so far, by its id: how often, when last, and
     * how often it failed this week.
     *
     * @return array<string, array{sent: int, last_sent_at: ?Carbon, failed: int}>
     */
    public function getStats(): array
    {
        $record = $this->getRecord();

        if (!$record instanceof Form || !$record->exists) {
            return [];
        }

        return FormSubmissionNotificationLog::query()
            ->toBase()
            ->whereIn('form_submission_id', FormSubmission::withTrashed()->where('form_id', $record->getKey())->select('id'))
            ->whereNotNull('notification_id')
            ->groupBy('notification_id')
            ->selectRaw(
                "notification_id, sum(case when status = 'sent' then 1 else 0 end) as sent, max(sent_at) as last_sent_at, sum(case when status = 'failed' and failed_at >= ? then 1 else 0 end) as failed",
                [now()->subWeek()],
            )
            ->get()
            ->mapWithKeys(fn (stdClass $row): array => [(string) $row->notification_id => [
                'sent' => (int) $row->sent,
                'last_sent_at' => filled($row->last_sent_at) ? Carbon::parse($row->last_sent_at) : null,
                'failed' => (int) $row->failed,
            ]])
            ->all();
    }

    /**
     * Each notification as its card shows it.
     *
     * @return array<string, array{subject: HtmlString, enabled: bool, to: list<array{label: string, isField: bool, isBroken: bool}>, replyTo: ?array{label: string, isField: bool, isBroken: bool}, conditions: ?string, attachFiles: bool}>
     */
    public function getCards(): array
    {
        $form = MergeTagEditor::form($this->getLivewire());
        $tags = array_map(
            fn (string $label): HtmlString => new HtmlString('<span class="ffb-mail-card-tag">' . e($label) . '</span>'),
            $form->getMergeTags(),
        );
        $conditions = new ConditionsEditor($this->getConditionFields($form));
        $fields = $form->getEmailRecipients();
        $recipient = fn (string $value): array => [
            'label' => $form->getRecipientLabel($value),
            'isField' => str_starts_with($value, EmailNotification::FIELD_PREFIX),
            'isBroken' => str_starts_with($value, EmailNotification::FIELD_PREFIX) && !isset($fields[$value]),
        ];
        $cards = [];

        foreach ($this->getRawState() ?? [] as $id => $item) {
            $cards[$id] = [
                'subject' => new HtmlString(strip_tags(MergeTags::render($item['subject'] ?? null, $tags), '<span>')),
                'enabled' => (bool) ($item['enabled'] ?? true),
                'to' => array_map($recipient, $item['to'] ?? []),
                'replyTo' => filled($item['reply_to'] ?? null) ? $recipient($item['reply_to']) : null,
                'conditions' => $conditions->badge($item['conditions'] ?? [], $item['conditionMatch'] ?? 'all'),
                'attachFiles' => (bool) ($item['attach_files'] ?? false),
            ];
        }

        return $cards;
    }

    /**
     * The tags the notifications hold for fields the form no longer has.
     *
     * @return list<string>
     */
    public function getMissingTags(): array
    {
        $known = MergeTagEditor::form($this->getLivewire())->getMergeTags();
        $missing = [];

        foreach ($this->getRawState() ?? [] as $item) {
            foreach (['subject', 'content', 'senderName'] as $field) {
                $missing = [...$missing, ...array_diff(MergeTags::ids($item[$field] ?? null), array_keys($known))];
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @return array<string, FormField>
     */
    protected function getConditionFields(Form $form): array
    {
        $fields = [];

        foreach ($form->getFields(inputsOnly: true) as $field) {
            $fields[$field->getKey()] = $field;
        }

        return $fields;
    }

    protected function hasUploads(Form $form): bool
    {
        foreach ($form->getFields(inputsOnly: true) as $field) {
            if ($field instanceof FileUploadField) {
                return true;
            }
        }

        return false;
    }
}
