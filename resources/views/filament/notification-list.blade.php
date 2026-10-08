@use('Filament\Support\Icons\Heroicon')

@php
    $key = $getKey();
    $cards = $field->getCards();
    $stats = $field->getStats();
    $missing = $field->getMissingTags();
    $isDisabled = $isDisabled();
    $mount = fn (string $action, array $arguments): ?string => $getAction($action)($arguments)->getLivewireClickHandler();
    $presets = [
        'empty' => Heroicon::OutlinedPlus,
        'confirmation' => Heroicon::OutlinedUser,
        'staff' => Heroicon::OutlinedInboxArrowDown,
    ];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="ffb-cards">
        @if ($missing !== [])
            <div class="ffb-cards-warning">
                <x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" class="ffb-cards-warning-icon" />
                <span>{{ trans_choice('filament-form-builder::general.merge_tags.unknown_warning', count($missing), ['tags' => implode(', ', $missing)]) }}</span>
            </div>
        @endif

        @foreach ($cards as $id => $card)
            @php
                $stat = $stats[$id] ?? null;
            @endphp

            <article
                wire:key="{{ $this->getId() }}.{{ $key }}.{{ $id }}"
                @class(['ffb-card', 'ffb-card-off' => ! $card['enabled']])
            >
                <header class="ffb-card-header">
                    <span class="ffb-card-icon">
                        <x-filament::icon :icon="Heroicon::OutlinedEnvelope" />
                    </span>

                    <button
                        type="button"
                        @if (! $isDisabled) wire:click="{{ $mount('edit', ['item' => $id]) }}" @endif
                        @disabled($isDisabled)
                        class="ffb-card-subject"
                    >
                        {{ $card['subject'] }}
                    </button>

                    @unless ($isDisabled)
                        <button
                            type="button"
                            role="switch"
                            aria-checked="{{ $card['enabled'] ? 'true' : 'false' }}"
                            aria-label="{{ __('filament-form-builder::general.notifications.enabled') }}"
                            title="{{ __('filament-form-builder::general.notifications.enabled') }}"
                            wire:click="{{ $mount('toggle', ['item' => $id]) }}"
                            class="ffb-card-switch"
                        >
                            <span></span>
                        </button>

                        <x-filament::dropdown placement="bottom-end">
                            <x-slot name="trigger">
                                <x-filament::icon-button
                                    :icon="Heroicon::EllipsisVertical"
                                    color="gray"
                                    :label="__('filament-form-builder::general.notifications.actions')"
                                />
                            </x-slot>

                            <x-filament::dropdown.list>
                                <x-filament::dropdown.list.item :icon="Heroicon::OutlinedPencilSquare" wire:click="{{ $mount('edit', ['item' => $id]) }}">
                                    {{ __('filament-form-builder::general.notifications.edit') }}
                                </x-filament::dropdown.list.item>

                                <x-filament::dropdown.list.item :icon="Heroicon::OutlinedDocumentDuplicate" wire:click="{{ $mount('clone', ['item' => $id]) }}">
                                    {{ __('filament-forms::components.builder.actions.clone.label') }}
                                </x-filament::dropdown.list.item>

                                <x-filament::dropdown.list.item :icon="Heroicon::OutlinedTrash" color="danger" wire:click="{{ $mount('delete', ['item' => $id]) }}">
                                    {{ __('filament-forms::components.builder.actions.delete.label') }}
                                </x-filament::dropdown.list.item>
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                    @endunless
                </header>

                <dl class="ffb-card-rows">
                    @foreach ($card['rows'] as $row)
                        <div class="ffb-card-row">
                            <dt>{{ $row['label'] }}</dt>
                            <dd>
                                @forelse ($row['recipients'] as $recipient)
                                    @include('filament-form-builder::filament.partials.recipient', ['recipient' => $recipient])
                                @empty
                                    <span class="ffb-card-muted">{{ __('filament-form-builder::general.notifications.no_recipients') }}</span>
                                @endforelse
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <footer class="ffb-card-footer">
                    @if ($card['conditions'] !== null)
                        <x-filament::badge :icon="Heroicon::OutlinedFunnel" size="sm">{{ $card['conditions'] }}</x-filament::badge>
                    @else
                        <x-filament::badge color="gray" size="sm">{{ __('filament-form-builder::general.notifications.when_always') }}</x-filament::badge>
                    @endif

                    @if (! $card['enabled'])
                        <span class="ffb-card-meta">{{ __('filament-form-builder::general.notifications.disabled') }}</span>
                    @endif

                    {{-- Only counted when the page loads; later renders leave these as they are. --}}
                    <span class="ffb-card-stats" wire:ignore>
                        @if (($stat['failed'] ?? 0) > 0)
                            <x-filament::badge color="danger" :icon="Heroicon::OutlinedExclamationTriangle" size="sm">
                                {{ trans_choice('filament-form-builder::general.notifications.failed_recently', $stat['failed'], ['count' => $stat['failed']]) }}
                            </x-filament::badge>
                        @endif

                        @if ($stat['last_sent_at'] ?? null)
                            <span class="ffb-card-meta">
                                <x-filament::icon :icon="Heroicon::OutlinedClock" />
                                {{ __('filament-form-builder::general.notifications.last_sent', ['date' => $stat['last_sent_at']->translatedFormat('j M, H:i')]) }}
                            </span>
                            <span class="ffb-card-meta">
                                <x-filament::icon :icon="Heroicon::OutlinedPaperAirplane" />
                                {{ trans_choice('filament-form-builder::general.notifications.sent_count', $stat['sent'], ['count' => $stat['sent']]) }}
                            </span>
                        @endif
                    </span>

                    @if ($card['attachFiles'])
                        <span class="ffb-card-meta">
                            <x-filament::icon :icon="Heroicon::OutlinedPaperClip" />
                            {{ __('filament-form-builder::general.notifications.with_attachments') }}
                        </span>
                    @endif
                </footer>
            </article>
        @endforeach

        @unless ($isDisabled)
            <div class="ffb-card-add">
                <span class="ffb-card-add-title">
                    <x-filament::icon :icon="Heroicon::OutlinedPlus" />
                    {{ __('filament-form-builder::general.notifications.add') }}
                </span>

                <div class="ffb-card-choices">
                    @foreach ($presets as $preset => $icon)
                        <button type="button" wire:click="{{ $mount('add', ['preset' => $preset]) }}" class="ffb-card-choice">
                            <x-filament::icon :icon="$icon" class="ffb-card-choice-icon" />
                            <span class="ffb-card-choice-title">{{ __("filament-form-builder::general.notifications.presets.{$preset}") }}</span>
                            <span class="ffb-card-choice-description">{{ __("filament-form-builder::general.notifications.presets.{$preset}_description") }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endunless
    </div>
</x-dynamic-component>
