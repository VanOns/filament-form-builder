<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;
use Livewire\Livewire as LivewireFacade;
use stdClass;
use Throwable;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Classes\MappedField;
use VanOns\FilamentFormBuilder\Classes\MergeTags;
use VanOns\FilamentFormBuilder\Enums\IntegrationStatus;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Models\FormSubmissionIntegrationLog;

/**
 * The integrations of a form as cards. Each is added by its type and set up
 * in a slide-over: its own settings, its fields and when it runs.
 */
class IntegrationList extends CardList
{
    protected string $view = 'filament-form-builder::filament.integration-list';

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (IntegrationList $component): Action => $component->getFormAction('add'),
            fn (IntegrationList $component): Action => $component->getFormAction('edit'),
            fn (IntegrationList $component): Action => $component->getChangeAction('clone', function (array $items, string $id): array {
                $copy = (string) Str::uuid();

                return [...$items, $copy => [...$items[$id], 'id' => $copy]];
            })
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedDocumentDuplicate)
                ->modalHeading(__('filament-form-builder::general.integrations.clone_heading'))
                ->modalDescription(__('filament-form-builder::general.integrations.clone_description'))
                ->modalSubmitActionLabel(__('filament-forms::components.builder.actions.clone.label')),
            fn (IntegrationList $component): Action => $component->getChangeAction('delete', fn (array $items, string $id): array => array_diff_key($items, [$id => true]))
                ->requiresConfirmation()
                ->color('danger')
                ->modalHeading(__('filament-form-builder::general.integrations.delete_heading'))
                ->modalDescription(__('filament-form-builder::general.integrations.delete_description'))
                ->modalSubmitActionLabel(__('filament-forms::components.builder.actions.delete.label')),
            fn (IntegrationList $component): Action => $component->getChangeAction('toggle', fn (array $items, string $id): array => [
                ...$items,
                $id => [...$items[$id], 'enabled' => !($items[$id]['enabled'] ?? true)],
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function prepare(array $item): array
    {
        $item = Integration::normalize($item);
        $item['id'] ??= (string) Str::uuid();

        return $item;
    }

    /**
     * The type an add is for, or of the integration an edit opens, as long as
     * the config still registers it.
     *
     * @param  array<string, mixed>  $arguments
     * @return class-string<Integration>|null
     */
    public function getIntegrationClass(array $arguments): ?string
    {
        $item = ($this->getRawState() ?? [])[$arguments['item'] ?? ''] ?? null;
        $class = $item === null ? ($arguments['class'] ?? null) : ($item['class'] ?? null);

        return Integration::isRegistered($class) ? Integration::resolve($class) : null;
    }

    public function getFormAction(string $name): Action
    {
        return Action::make($name)
            ->modalHeading(fn (array $arguments, IntegrationList $component): ?string => ($class = $component->getIntegrationClass($arguments)) === null
                ? null
                : __("filament-form-builder::general.integrations.{$name}_heading", ['label' => $class::label()]))
            ->modalIcon(fn (array $arguments, IntegrationList $component): string|BackedEnum => ($class = $component->getIntegrationClass($arguments)) === null ? Heroicon::OutlinedPuzzlePiece : $class::icon())
            ->modalDescription(fn (array $arguments, IntegrationList $component): ?string => ($class = $component->getIntegrationClass($arguments)) === null ? null : $class::description())
            ->slideOver()
            ->modalSubmitActionLabel(__($name === 'add' ? 'filament-form-builder::general.integrations.add_submit' : 'filament-form-builder::general.save'))
            ->fillForm(fn (array $arguments, IntegrationList $component): ?array => $component->getFormData($arguments))
            ->schema(fn (array $arguments, IntegrationList $component, Livewire $livewire): array => $component->getIntegrationSchema($arguments, $livewire))
            ->modalFooterActions(function (Action $action, array $arguments, IntegrationList $component): array {
                $actions = [$action->getModalSubmitAction(), $action->getModalCancelAction()];

                $class = $component->getIntegrationClass($arguments);

                if ($class === null || !$class::isTestable()) {
                    return $actions;
                }

                $canTest = $component->getLatestSubmission() !== null;

                return [
                    ...$actions,
                    $action->makeModalSubmitAction('runTest', arguments: ['test' => true])
                        ->label(__('filament-form-builder::general.integrations.test'))
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->color('gray')
                        ->disabled(!$canTest)
                        ->tooltip($canTest ? null : __('filament-form-builder::general.integrations.test_unavailable'))
                        ->extraAttributes(['class' => 'ffb-modal-action-end']),
                ];
            })
            ->action(function (array $arguments, array $data, IntegrationList $component, Action $action): void {
                $class = $component->getIntegrationClass($arguments);

                if ($class === null) {
                    return;
                }

                if ($arguments['test'] ?? false) {
                    $component->runTest($class, $data);
                    $action->halt();
                }

                $component->saveItem(is_string($arguments['item'] ?? null) ? $arguments['item'] : null, $class, $data);
            });
    }

    /**
     * A new integration gets none, so the slide-over starts from the defaults
     * of its fields; Filament skips those as soon as it is given any state.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null
     */
    public function getFormData(array $arguments): ?array
    {
        $item = ($this->getRawState() ?? [])[$arguments['item'] ?? ''] ?? null;

        if ($item === null) {
            return null;
        }

        return [
            ...$item,
            'when' => filled($item['conditions'] ?? []) ? 'conditions' : 'always',
        ];
    }

    /**
     * @param  class-string<Integration>  $class
     * @param  array<string, mixed>  $data
     */
    public function saveItem(?string $id, string $class, array $data): void
    {
        $items = $this->getRawState() ?? [];
        $id ??= (string) Str::uuid();

        // The slide-over holds every setting, so what it leaves out is gone, such as a token after switching to no security.
        $items[$id] = $this->prepare([
            ...Arr::except($this->fromSlideOver($class, $data), ['id', 'class', 'enabled']),
            'id' => $id,
            'class' => $class,
            'enabled' => $items[$id]['enabled'] ?? true,
        ]);

        if ($this->store($items)) {
            Notification::make()
                ->success()
                ->title(__('filament-form-builder::general.integrations.saved'))
                ->send();
        }
    }

    /**
     * @param  class-string<Integration>  $class
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fromSlideOver(string $class, array $data): array
    {
        if (!$class::hasConditions() || ($data['when'] ?? 'always') !== 'conditions') {
            $data['conditions'] = [];
        }

        $keys = array_map(fn (MappedField $field): string => $field->key, $class::fields());

        // An emptied text field still holds an empty paragraph.
        $data['mapping'] = array_filter(
            Arr::only(is_array($data['mapping'] ?? null) ? $data['mapping'] : [], $keys),
            fn (mixed $value): bool => is_string($value) && (trim(strip_tags($value)) !== '' || MergeTags::ids($value) !== []),
        );

        unset($data['when']);

        return $data;
    }

    /**
     * Saves the integrations with their form right away when it exists, so a
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

        return $record->update(['integrations' => array_values($items)]);
    }

    /**
     * Runs the integration as the slide-over has it, with the latest
     * submission, and says how it went; nothing is logged.
     *
     * @param  class-string<Integration>  $class
     * @param  array<string, mixed>  $data
     */
    public function runTest(string $class, array $data): void
    {
        $submission = $this->getLatestSubmission();

        if ($submission === null) {
            return;
        }

        $integration = new $class($submission, [...$this->fromSlideOver($class, $data), 'class' => $class]);

        try {
            $integration->handle();
            $error = $integration->success === false ? ($integration->error ?? __('filament-form-builder::general.failed')) : null;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        Notification::make()
            ->status($error === null ? 'success' : 'danger')
            ->title(__($error === null ? 'filament-form-builder::general.integrations.test_succeeded' : 'filament-form-builder::general.integrations.test_failed'))
            ->body($error)
            ->send();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<Component>
     */
    public function getIntegrationSchema(array $arguments, Livewire $livewire): array
    {
        $class = $this->getIntegrationClass($arguments);

        if ($class === null) {
            return [];
        }

        $form = MergeTagEditor::form($livewire);
        $own = [];
        $groups = [];

        // An integration may head groups of its own; the rest are its settings.
        foreach ($class::schema() as $component) {
            if ($component instanceof SettingsGroup) {
                $groups[] = $component;
            } else {
                $own[] = $component;
            }
        }

        if ($own !== []) {
            array_unshift($groups, SettingsGroup::make(__('filament-form-builder::general.integrations.settings'))->schema($own));
        }

        if ($class::fields() !== []) {
            $suggested = $this->suggestMapping($class, $form);

            $groups[] = SettingsGroup::make(__('filament-form-builder::general.integrations.mapping'))
                ->description(__('filament-form-builder::general.integrations.mapping_description'))
                ->columns(2)
                ->schema(array_map(fn (MappedField $field): Field => $this->getMappingInput($field, $form)->default($suggested[$field->key] ?? null), $class::fields()));
        }

        if ($class::hasConditions()) {
            $groups[] = SettingsGroup::make(__('filament-form-builder::general.integrations.when'))
                ->schema([
                    ToggleButtons::make('when')
                        ->label(__('filament-form-builder::general.integrations.when'))
                        ->hiddenLabel()
                        ->options([
                            'always' => __('filament-form-builder::general.integrations.when_always'),
                            'conditions' => __('filament-form-builder::general.integrations.when_conditions'),
                        ])
                        ->default('always')
                        ->grouped()
                        ->live(),
                    Group::make((new ConditionsEditor(
                        ConditionsEditor::fieldsOf($form),
                        'filament-form-builder::general.integrations.conditions_summary',
                        'filament-form-builder::general.integrations.condition_match',
                    ))->schema())
                        ->visible(fn (Get $get): bool => $get('when') === 'conditions'),
                ]);
        }

        foreach (array_slice($groups, 1) as $group) {
            $group->extraAttributes(['class' => 'ffb-settings-divided'], merge: true);
        }

        return $groups;
    }

    protected function getMappingInput(MappedField $field, Form $form): Field
    {
        if ($field->isText()) {
            return MergeTagEditor::line("mapping.{$field->key}")
                ->label($field->label)
                ->helperText($field->getHelperText() ?? __('filament-form-builder::general.integrations.mapping_text_helper'))
                ->required($field->isRequired())
                ->columnSpanFull();
        }

        return Select::make("mapping.{$field->key}")
            ->label($field->label)
            ->options($this->getMappingOptions($field, $form))
            ->placeholder(__('filament-form-builder::general.integrations.not_mapped'))
            ->helperText($field->getHelperText())
            ->required($field->isRequired());
    }

    /**
     * @return array<string, string>
     */
    public function getMappingOptions(MappedField $field, Form $form): array
    {
        if (!$field->isEmail()) {
            return $form->getSubmissionFields();
        }

        $options = [];

        foreach ($form->getEmailRecipients() as $recipient => $label) {
            $options[(string) EmailNotification::fieldKey($recipient)] = $label;
        }

        return $options;
    }

    /**
     * For a new integration: each field it asks for gets the form field of the
     * same name, and an e-mail address the first field that holds one.
     *
     * @param  class-string<Integration>  $class
     * @return array<string, string>
     */
    public function suggestMapping(string $class, Form $form): array
    {
        $mapping = [];

        foreach ($class::fields() as $field) {
            if ($field->isText()) {
                continue;
            }

            $options = $this->getMappingOptions($field, $form);
            $wanted = [Str::slug($field->key), Str::slug($field->label)];
            $match = Arr::first(array_keys($options), fn (int|string $key): bool => array_intersect([Str::slug((string) $key), Str::slug($options[$key])], $wanted) !== []);
            $match ??= $field->isEmail() ? array_key_first($options) : null;

            if ($match !== null) {
                $mapping[$field->key] = (string) $match;
            }
        }

        return $mapping;
    }

    /**
     * The types a form can add, as the add tiles show them.
     *
     * @return array<class-string<Integration>, array{label: string, description: ?string, icon: string|BackedEnum}>
     */
    public function getChoices(): array
    {
        $choices = [];

        foreach (Integration::getIntegrations() as $class) {
            $choices[$class] = ['label' => $class::label(), 'description' => $class::description(), 'icon' => $class::icon()];
        }

        return $choices;
    }

    /**
     * What each integration ran so far, by its id: how often it succeeded,
     * when last, and how often it failed this week. Counted when the page
     * loads only, not for every change made on it.
     *
     * @return array<string, array{succeeded: int, last_ran_at: ?Carbon, failed: int}>
     */
    public function getStats(): array
    {
        $record = $this->getRecord();

        if (!$record instanceof Form || !$record->exists || LivewireFacade::isLivewireRequest()) {
            return [];
        }

        return FormSubmissionIntegrationLog::query()
            ->toBase()
            ->whereIn('form_submission_id', FormSubmission::withTrashed()->where('form_id', $record->getKey())->select('id'))
            ->groupBy('integration_id')
            ->selectRaw(
                'integration_id, sum(case when status = ? then 1 else 0 end) as succeeded, max(ran_at) as last_ran_at, sum(case when status = ? and ran_at >= ? then 1 else 0 end) as failed',
                [IntegrationStatus::Succeeded->value, IntegrationStatus::Failed->value, now()->subWeek()],
            )
            ->get()
            ->mapWithKeys(fn (stdClass $row): array => [(string) $row->integration_id => [
                'succeeded' => (int) $row->succeeded,
                'last_ran_at' => filled($row->last_ran_at) ? Carbon::parse($row->last_ran_at) : null,
                'failed' => (int) $row->failed,
            ]])
            ->all();
    }

    /**
     * Each integration as its card shows it.
     *
     * @return array<string, array{label: string, summary: ?string, icon: string|BackedEnum, enabled: bool, isKnown: bool, rows: list<array{source: string|HtmlString, target: string, isBroken: bool, isText: bool}>, conditions: ?string, hasConditions: bool}>
     */
    public function getCards(): array
    {
        $form = MergeTagEditor::form($this->getLivewire());
        $fields = $form->getSubmissionFields();
        $conditions = new ConditionsEditor(ConditionsEditor::fieldsOf($form));
        $tags = null;
        $cards = [];

        foreach ($this->getRawState() ?? [] as $id => $item) {
            $class = Integration::resolve($item['class'] ?? null);
            $rows = [];

            foreach ($class === null ? [] : $class::fields() as $field) {
                $source = $item['mapping'][$field->key] ?? null;

                if (!is_string($source) || $source === '') {
                    continue;
                }

                if ($field->isText()) {
                    $tags ??= static::getTagChips($form);
                    $rows[] = ['source' => new HtmlString(strip_tags(MergeTags::render($source, $tags), '<span><svg><path>')), 'target' => $field->label, 'isBroken' => false, 'isText' => true];
                } else {
                    $rows[] = ['source' => $fields[$source] ?? __('filament-form-builder::general.merge_tags.missing', ['key' => $source]), 'target' => $field->label, 'isBroken' => !isset($fields[$source]), 'isText' => false];
                }
            }

            $cards[$id] = [
                'label' => $class === null ? __('filament-form-builder::general.integrations.unknown') : $class::label(),
                'summary' => $class === null ? ($item['class'] ?? null ?: null) : $class::summary($item),
                'icon' => $class === null ? Heroicon::OutlinedPuzzlePiece : $class::icon(),
                'enabled' => (bool) ($item['enabled'] ?? true),
                'isKnown' => $class !== null && Integration::isRegistered($class),
                'rows' => $rows,
                'conditions' => $conditions->badge($item['conditions'] ?? [], $item['conditionMatch'] ?? 'all'),
                'hasConditions' => $class !== null && $class::hasConditions(),
            ];
        }

        return $cards;
    }
}
