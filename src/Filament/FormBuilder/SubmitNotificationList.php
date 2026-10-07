<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;
use VanOns\FilamentFormBuilder\Classes\MergeTags;
use VanOns\FilamentFormBuilder\Classes\SubmitNotification;
use VanOns\FilamentFormBuilder\Enums\SubmitNotificationType;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;

/**
 * The outcomes for certain answers as cards, tried from the top; each edited
 * in a slide-over. They are saved with the form.
 */
class SubmitNotificationList extends Field
{
    protected string $view = 'filament-form-builder::filament.submit-notification-list';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->afterStateHydrated(static function (SubmitNotificationList $component, ?array $rawState): void {
            $items = array_map(SubmitNotification::normalize(...), array_values(array_filter($rawState ?? [], is_array(...))));

            $component->rawState(array_column($items, null, 'id'));
        });

        $this->mutateDehydratedStateUsing(static fn (?array $state): array => array_values($state ?? []));

        $this->registerActions([
            fn (SubmitNotificationList $component): Action => $component->getFormAction('add'),
            fn (SubmitNotificationList $component): Action => $component->getFormAction('edit'),
            fn (SubmitNotificationList $component): Action => $component->getChangeAction('clone', function (array $items, string $id): array {
                $copy = [...$items[$id], 'id' => (string) Str::uuid()];

                return static::insertAfter($items, $id, $copy);
            }),
            fn (SubmitNotificationList $component): Action => $component->getChangeAction('delete', fn (array $items, string $id): array => array_diff_key($items, [$id => true]))
                ->requiresConfirmation()
                ->color('danger')
                ->modalHeading(__('filament-form-builder::general.submit_rules.delete_heading'))
                ->modalDescription(__('filament-form-builder::general.submit_rules.delete_description'))
                ->modalSubmitActionLabel(__('filament-forms::components.builder.actions.delete.label')),
            fn (SubmitNotificationList $component): Action => $component->getChangeAction('moveUp', fn (array $items, string $id): array => static::move($items, $id, -1)),
            fn (SubmitNotificationList $component): Action => $component->getChangeAction('moveDown', fn (array $items, string $id): array => static::move($items, $id, 1)),
        ]);
    }

    public function getFormAction(string $name): Action
    {
        return Action::make($name)
            ->modalHeading(__("filament-form-builder::general.submit_rules.{$name}_heading"))
            ->modalIcon(Heroicon::OutlinedFunnel)
            ->slideOver()
            ->modalSubmitActionLabel(__('filament-form-builder::general.save'))
            ->fillForm(fn (array $arguments, Livewire $livewire): array => ($this->getRawState() ?? [])[$arguments['item'] ?? '']
                ?? ['type' => array_key_first(SubmitNotifications::getTypes(SubmitNotifications::getFormType($livewire)))])
            ->schema(fn (Livewire $livewire): array => [
                Section::make(__('filament-form-builder::general.submit_rules.if'))
                    ->contained(false)
                    ->extraAttributes(['class' => 'ffb-mail-form-group'])
                    ->schema((new ConditionsEditor(
                        ConditionsEditor::fieldsOf(MergeTagEditor::form($livewire)),
                        'filament-form-builder::general.submit_rules.conditions_summary',
                        'filament-form-builder::general.submit_rules.condition_match',
                        isRequired: true,
                    ))->schema()),
                Section::make(__('filament-form-builder::general.submit_rules.then'))
                    ->contained(false)
                    ->extraAttributes(['class' => 'ffb-mail-form-group ffb-mail-form-divided'])
                    ->schema(SubmitNotifications::getOutcomeSchema()),
            ])
            ->action(function (array $arguments, array $data): void {
                $items = $this->getRawState() ?? [];
                $id = is_string($arguments['item'] ?? null) ? $arguments['item'] : (string) Str::uuid();

                $items[$id] = SubmitNotification::normalize([...$items[$id] ?? [], ...$data, 'id' => $id]);

                $this->rawState($items);
            });
    }

    /**
     * An action that changes the list around one item.
     *
     * @param  \Closure(array<string, array<string, mixed>>, string): array<string, array<string, mixed>>  $change
     */
    public function getChangeAction(string $name, \Closure $change): Action
    {
        return Action::make($name)
            ->action(function (array $arguments) use ($change): void {
                $items = $this->getRawState() ?? [];
                $id = (string) ($arguments['item'] ?? '');

                if (isset($items[$id])) {
                    $this->rawState($change($items, $id));
                }
            });
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    public static function move(array $items, string $id, int $step): array
    {
        $ids = array_keys($items);
        $from = (int) array_search($id, $ids, true);
        $to = max(0, min(count($ids) - 1, $from + $step));

        array_splice($ids, $from, 1);
        array_splice($ids, $to, 0, [$id]);

        return array_replace(array_flip($ids), $items);
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @param  array<string, mixed>  $item
     * @return array<string, array<string, mixed>>
     */
    public static function insertAfter(array $items, string $id, array $item): array
    {
        $position = (int) array_search($id, array_keys($items), true) + 1;

        return [
            ...array_slice($items, 0, $position, preserve_keys: true),
            $item['id'] => $item,
            ...array_slice($items, $position, preserve_keys: true),
        ];
    }

    /**
     * What happens when none of the cards holds: the outcome above them.
     */
    public function getOtherwise(): string
    {
        $type = data_get($this->getLivewire(), Str::beforeLast($this->getStatePath(), '.') . '.default.type');

        return $type === SubmitNotificationType::URL->value || $type === SubmitNotificationType::URL
            ? __('filament-form-builder::general.submit_rules.otherwise_url')
            : __('filament-form-builder::general.submit_rules.otherwise_content');
    }

    /**
     * Each outcome as its card shows it: when, and what then.
     *
     * @return array<string, array{when: ?string, then: string, isRedirect: bool}>
     */
    public function getCards(): array
    {
        $form = MergeTagEditor::form($this->getLivewire());
        $conditions = new ConditionsEditor(ConditionsEditor::fieldsOf($form));
        $cards = [];

        foreach ($this->getRawState() ?? [] as $id => $item) {
            $isRedirect = ($item['type'] ?? null) === SubmitNotificationType::URL->value;
            $url = $isRedirect ? FilamentFormBuilderPlugin::resolveRedirectUrl($item['url'] ?? null, $form) : null;
            $text = Str::limit(MergeTags::render($item['content'] ?? null, $form->getMergeTags(), asText: true), 60);

            $cards[$id] = [
                'when' => $conditions->badge($item['conditions'] ?? [], $item['conditionMatch'] ?? 'all'),
                'then' => $isRedirect
                    ? (filled($url) ? __('filament-form-builder::general.submit_rules.redirect', ['url' => $url]) : __('filament-form-builder::general.submit_rules.redirect_unknown'))
                    : __('filament-form-builder::general.submit_rules.message', ['text' => $text]),
                'isRedirect' => $isRedirect,
            ];
        }

        return $cards;
    }
}
