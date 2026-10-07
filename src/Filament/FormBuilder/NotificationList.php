<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

use function Filament\Support\generate_icon_html;

use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
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
            ->modalDescription(fn (array $arguments, NotificationList $component, Livewire $livewire): ?string => $component->describe($arguments, $livewire))
            ->slideOver()
            ->modalSubmitActionLabel(__('filament-form-builder::general.save'))
            ->fillForm(fn (array $arguments, NotificationList $component, Livewire $livewire): array => $component->getFormData($arguments, $livewire))
            ->schema(fn (NotificationList $component, Livewire $livewire): array => $component->getNotificationSchema($livewire))
            ->modalFooterActions(fn (Action $action, NotificationList $component): array => [
                $action->getModalSubmitAction(),
                $action->getModalCancelAction(),
                $action->makeModalSubmitAction('sendTest', arguments: ['test' => true])
                    ->label(__('filament-form-builder::general.notifications.send_test'))
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('gray')
                    ->disabled($component->getLatestSubmission() === null)
                    ->tooltip($component->getLatestSubmission() === null ? __('filament-form-builder::general.notifications.send_test_unavailable') : null)
                    ->extraAttributes(['class' => 'ffb-modal-action-end']),
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
                    $component->store([...$items, $id => [...$item, 'id' => $id]]);
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

                $component->store($items);
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
                    $component->store($items);
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

        return [
            ...$item,
            'when' => filled($item['conditions'] ?? []) ? 'conditions' : 'always',
            'show_copies' => filled($item['cc'] ?? []) || filled($item['bcc'] ?? []),
            'show_sender' => filled($item['sender'] ?? null) || filled(strip_tags((string) ($item['senderName'] ?? ''))) || MergeTags::ids($item['senderName'] ?? null) !== [],
        ];
    }

    /**
     * Under the slide-over's heading: who an existing notification goes to
     * and when, or what a preset starts with.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function describe(array $arguments, Livewire $livewire): ?string
    {
        $item = ($this->getRawState() ?? [])[$arguments['item'] ?? ''] ?? null;

        if ($item === null) {
            $preset = (string) ($arguments['preset'] ?? '');

            return in_array($preset, ['confirmation', 'staff'], true)
                ? __("filament-form-builder::general.notifications.presets.{$preset}_description")
                : null;
        }

        $form = MergeTagEditor::form($livewire);
        $recipients = array_map(fn (string $recipient): string => $form->getRecipientLabel($recipient), $item['to'] ?? []);

        if ($recipients === []) {
            return __('filament-form-builder::general.notifications.no_recipients');
        }

        return __(filled($item['conditions'] ?? []) ? 'filament-form-builder::general.notifications.description_conditions' : 'filament-form-builder::general.notifications.description_always', [
            'recipients' => Arr::join($recipients, ', ', __('filament-form-builder::general.notifications.list_and')),
        ]);
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
        $data = $this->fromSlideOver($data);

        $items = $this->getRawState() ?? [];
        $id ??= (string) Str::uuid();
        $items[$id] = $this->prepare([...$items[$id] ?? [], ...$data, 'id' => $id]);

        if ($this->store($items)) {
            Notification::make()
                ->success()
                ->title(__('filament-form-builder::general.notifications.saved'))
                ->send();
        }
    }

    /**
     * The slide-over's choices as the notification stores them: "always"
     * drops the conditions, and fields never brought up mean no copies and the
     * default sender.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fromSlideOver(array $data): array
    {
        if (($data['when'] ?? 'always') !== 'conditions') {
            $data['conditions'] = [];
        }

        if (!($data['show_copies'] ?? false)) {
            $data['cc'] = [];
            $data['bcc'] = [];
        }

        if (!($data['show_sender'] ?? false)) {
            $data['sender'] = null;
            $data['senderName'] = null;
        }

        unset($data['when'], $data['show_copies'], $data['show_sender']);

        return $data;
    }

    /**
     * Saves the notifications with their form right away when it exists, so a
     * change does not wait for the page; a form being created waits for that.
     *
     * @param  array<string, array<string, mixed>>  $items
     */
    public function store(array $items): bool
    {
        $this->rawState($items);

        $record = $this->getRecord();

        if (!$record instanceof Form || !$record->exists) {
            return false;
        }

        return $record->update(['notifications' => array_values($items)]);
    }

    /**
     * @return array<Component>
     */
    public function getNotificationSchema(Livewire $livewire): array
    {
        $form = MergeTagEditor::form($livewire);
        $group = fn (string $heading): Section => Section::make(__("filament-form-builder::general.notifications.{$heading}"))
            ->contained(false)
            ->extraAttributes(['class' => 'ffb-mail-form-group']);

        return [
            $group('recipients')->schema([
                RecipientsInput::make('to')
                    ->label(__('filament-form-builder::general.notifications.to'))
                    ->helperText(__('filament-form-builder::general.notifications.to_helper'))
                    ->form($form)
                    ->required()
                    ->validationMessages(['required' => __('filament-form-builder::general.notifications.to_required')]),
                Hidden::make('show_copies'),
                Actions::make([
                    Action::make('addCopies')
                        ->label(__('filament-form-builder::general.notifications.add_copies'))
                        ->icon(Heroicon::Plus)
                        ->link()
                        ->action(fn (Set $set) => $set('show_copies', true)),
                ])->visible(fn (Get $get): bool => !$get('show_copies')),
                RecipientsInput::make('cc')
                    ->label(__('filament-form-builder::general.notifications.cc'))
                    ->helperText(__('filament-form-builder::general.notifications.cc_helper'))
                    ->form($form)
                    ->visible(fn (Get $get): bool => (bool) $get('show_copies')),
                RecipientsInput::make('bcc')
                    ->label(__('filament-form-builder::general.notifications.bcc'))
                    ->helperText(__('filament-form-builder::general.notifications.bcc_helper'))
                    ->form($form)
                    ->visible(fn (Get $get): bool => (bool) $get('show_copies')),
                RecipientsInput::make('reply_to')
                    ->label(__('filament-form-builder::general.notifications.reply_to'))
                    ->helperText(__('filament-form-builder::general.notifications.reply_to_helper'))
                    ->form($form)
                    ->multiple(false),
            ]),
            $group('message')
                ->extraAttributes(['class' => 'ffb-mail-form-divided'], merge: true)
                ->schema([
                    MergeTagEditor::line('subject')
                        ->label(__('filament-form-builder::general.notifications.subject'))
                        ->required(),
                    MergeTagEditor::make('content')
                        ->label(__('filament-form-builder::general.notifications.content'))
                        ->required(),
                ]),
            $group('when')
                ->extraAttributes(['class' => 'ffb-mail-form-divided'], merge: true)
                ->schema([
                    ToggleButtons::make('when')
                        ->label(__('filament-form-builder::general.notifications.when'))
                        ->hiddenLabel()
                        ->options([
                            'always' => __('filament-form-builder::general.notifications.when_always'),
                            'conditions' => __('filament-form-builder::general.notifications.when_conditions'),
                        ])
                        ->default('always')
                        ->grouped()
                        ->live(),
                    Group::make((new ConditionsEditor(
                        ConditionsEditor::fieldsOf($form),
                        'filament-form-builder::general.notifications.conditions_summary',
                        'filament-form-builder::general.notifications.condition_match',
                    ))->schema())
                        ->visible(fn (Get $get): bool => $get('when') === 'conditions'),
                ]),
            $group('attachments')
                ->extraAttributes(['class' => 'ffb-mail-form-divided'], merge: true)
                ->visible($this->hasUploads($form))
                ->schema([
                    Toggle::make('attach_files')
                        ->label(__('filament-form-builder::general.notifications.attach_files'))
                        ->helperText(__('filament-form-builder::general.notifications.attach_files_helper', [
                            'size' => Number::fileSize((int) config('filament-form-builder.uploads.attach_max_size', 10240) * 1024),
                        ])),
                ]),
            ...$this->getSenderSchema(),
        ];
    }

    /**
     * The default sender as one line, with the fields to change it behind a
     * link.
     *
     * @return array<Component>
     */
    protected function getSenderSchema(): array
    {
        $default = trim(config('mail.from.name') . ' <' . config('mail.from.address') . '>');

        return [
            Hidden::make('show_sender'),
            Flex::make([
                Icon::make(Heroicon::OutlinedAtSymbol)->color('gray')->grow(false),
                Text::make(fn (Get $get): string => $get('show_sender')
                    ? __('filament-form-builder::general.notifications.sender')
                    : __('filament-form-builder::general.notifications.sender_default', ['sender' => $default]))
                    ->grow(false),
                Actions::make([
                    Action::make('changeSender')
                        ->label(fn (Get $get): string => $get('show_sender')
                            ? __('filament-form-builder::general.notifications.sender_reset')
                            : __('filament-form-builder::general.notifications.sender_change'))
                        ->link()
                        ->action(function (Get $get, Set $set): void {
                            if ($get('show_sender')) {
                                $set('sender', null);
                                $set('senderName', null);
                            }

                            $set('show_sender', !$get('show_sender'));
                        }),
                ])->grow(false),
            ])
                ->verticallyAlignCenter()
                ->extraAttributes(['class' => 'ffb-mail-sender-line ffb-mail-form-divided']),
            Group::make([
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
            ])->visible(fn (Get $get): bool => (bool) $get('show_sender')),
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

        $notification = new EmailNotification($submission, $this->fromSlideOver($data));

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
     * @return array<string, array{subject: HtmlString, enabled: bool, rows: list<array{label: string, recipients: list<array{label: string, isField: bool, isBroken: bool}>}>, conditions: ?string, attachFiles: bool}>
     */
    public function getCards(): array
    {
        $form = MergeTagEditor::form($this->getLivewire());
        $tags = [];

        foreach ($form->getMergeTagGroups() as $group) {
            foreach ($group['tags'] as $id => $tag) {
                $icon = generate_icon_html($tag['icon'])?->toHtml() ?? '';
                $tags[$id] = new HtmlString('<span class="ffb-mail-card-tag"><span class="ffb-mail-card-tag-icon">' . $icon . '</span>' . e($tag['label']) . '</span>');
            }
        }

        $conditions = new ConditionsEditor(ConditionsEditor::fieldsOf($form));
        $fields = $form->getEmailRecipients();
        $recipient = fn (string $value): array => [
            'label' => $form->getRecipientLabel($value),
            'isField' => str_starts_with($value, EmailNotification::FIELD_PREFIX),
            'isBroken' => str_starts_with($value, EmailNotification::FIELD_PREFIX) && !isset($fields[$value]),
        ];
        $cards = [];

        foreach ($this->getRawState() ?? [] as $id => $item) {
            $rows = [['label' => __('filament-form-builder::general.notifications.to'), 'recipients' => array_map($recipient, $item['to'] ?? [])]];

            foreach (['cc' => $item['cc'] ?? [], 'bcc' => $item['bcc'] ?? [], 'reply_to' => array_filter([$item['reply_to'] ?? null])] as $row => $recipients) {
                if ($recipients !== []) {
                    $rows[] = ['label' => __("filament-form-builder::general.notifications.{$row}"), 'recipients' => array_values(array_map($recipient, $recipients))];
                }
            }

            $cards[$id] = [
                'subject' => new HtmlString(strip_tags(MergeTags::render($item['subject'] ?? null, $tags), '<span><svg><path>')),
                'enabled' => (bool) ($item['enabled'] ?? true),
                'rows' => $rows,
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
