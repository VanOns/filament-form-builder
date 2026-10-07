<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Actions\Action;
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
class SubmitNotificationList extends CardList
{
    protected string $view = 'filament-form-builder::filament.submit-notification-list';

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (SubmitNotificationList $component): Action => $component->getFormAction('add'),
            fn (SubmitNotificationList $component): Action => $component->getFormAction('edit'),
            fn (SubmitNotificationList $component): Action => $component->getChangeAction('clone', fn (array $items, string $id): array => static::insertAfter($items, $id, [...$items[$id], 'id' => (string) Str::uuid()])),
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

    public function prepare(array $item): array
    {
        return SubmitNotification::normalize($item);
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
                    ->extraAttributes(['class' => 'ffb-settings-group'])
                    ->schema((new ConditionsEditor(
                        ConditionsEditor::fieldsOf(MergeTagEditor::form($livewire)),
                        'filament-form-builder::general.submit_rules.conditions_summary',
                        'filament-form-builder::general.submit_rules.condition_match',
                        isRequired: true,
                    ))->schema()),
                Section::make(__('filament-form-builder::general.submit_rules.then'))
                    ->contained(false)
                    ->extraAttributes(['class' => 'ffb-settings-group ffb-mail-form-divided'])
                    ->schema(SubmitNotifications::getOutcomeSchema()),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->putItem(is_string($arguments['item'] ?? null) ? $arguments['item'] : null, $data);
            });
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
