@use('Filament\Support\Icons\Heroicon')

@php
    $key = $getKey();
    $cards = $field->getCards();
    $stats = $field->getStats();
    $choices = $field->getChoices();
    $isDisabled = $isDisabled();
    $mount = fn (string $action, array $arguments): ?string => $getAction($action)($arguments)->getLivewireClickHandler();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="ffb-cards">
        @foreach ($cards as $id => $card)
            @php
                $stat = $stats[$id] ?? null;
                $canEdit = ! $isDisabled && $card['isKnown'];
            @endphp

            <article
                wire:key="{{ $this->getId() }}.{{ $key }}.{{ $id }}"
                @class(['ffb-card', 'ffb-card-off' => ! $card['enabled'] || ! $card['isKnown']])
            >
                <header class="ffb-card-header">
                    <span class="ffb-card-icon">
                        <x-filament::icon :icon="$card['icon']" />
                    </span>

                    <button
                        type="button"
                        @if ($canEdit) wire:click="{{ $mount('edit', ['item' => $id]) }}" @endif
                        @disabled(! $canEdit)
                        class="ffb-card-subject"
                    >
                        {{ $card['label'] }}

                        @if (filled($card['summary']))
                            <span class="ffb-card-summary">{{ $card['summary'] }}</span>
                        @endif
                    </button>

                    @unless ($isDisabled)
                        @if ($card['isKnown'])
                            <button
                                type="button"
                                role="switch"
                                aria-checked="{{ $card['enabled'] ? 'true' : 'false' }}"
                                aria-label="{{ __('filament-form-builder::general.integrations.enabled') }}"
                                title="{{ __('filament-form-builder::general.integrations.enabled') }}"
                                wire:click="{{ $mount('toggle', ['item' => $id]) }}"
                                class="ffb-card-switch"
                            >
                                <span></span>
                            </button>
                        @endif

                        <x-filament::dropdown placement="bottom-end">
                            <x-slot name="trigger">
                                <x-filament::icon-button
                                    :icon="Heroicon::EllipsisVertical"
                                    color="gray"
                                    :label="__('filament-form-builder::general.integrations.actions')"
                                />
                            </x-slot>

                            <x-filament::dropdown.list>
                                @if ($card['isKnown'])
                                    <x-filament::dropdown.list.item :icon="Heroicon::OutlinedPencilSquare" wire:click="{{ $mount('edit', ['item' => $id]) }}">
                                        {{ __('filament-form-builder::general.integrations.edit') }}
                                    </x-filament::dropdown.list.item>

                                    <x-filament::dropdown.list.item :icon="Heroicon::OutlinedDocumentDuplicate" wire:click="{{ $mount('clone', ['item' => $id]) }}">
                                        {{ __('filament-forms::components.builder.actions.clone.label') }}
                                    </x-filament::dropdown.list.item>
                                @endif

                                <x-filament::dropdown.list.item :icon="Heroicon::OutlinedTrash" color="danger" wire:click="{{ $mount('delete', ['item' => $id]) }}">
                                    {{ __('filament-forms::components.builder.actions.delete.label') }}
                                </x-filament::dropdown.list.item>
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                    @endunless
                </header>

                @if ($card['rows'] !== [])
                    <ul class="ffb-card-rows ffb-card-mapping">
                        @foreach ($card['rows'] as $row)
                            <li class="ffb-card-mapping-row">
                                @if ($row['isText'])
                                    <span class="ffb-card-mapping-text">{{ $row['source'] }}</span>
                                @else
                                    <span @class(['ffb-recipient', 'ffb-recipient-field' => ! $row['isBroken'], 'ffb-recipient-broken' => $row['isBroken']])>
                                        @if ($row['isBroken'])
                                            <x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" class="ffb-recipient-icon" />
                                        @endif
                                        {{ $row['source'] }}
                                    </span>
                                @endif

                                <x-filament::icon :icon="Heroicon::ArrowLongRight" class="ffb-card-mapping-arrow" />
                                <span>{{ $row['target'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <footer class="ffb-card-footer">
                    @if ($card['hasConditions'])
                        @if ($card['conditions'] !== null)
                            <x-filament::badge :icon="Heroicon::OutlinedFunnel" size="sm">{{ $card['conditions'] }}</x-filament::badge>
                        @else
                            <x-filament::badge color="gray" size="sm">{{ __('filament-form-builder::general.integrations.when_always') }}</x-filament::badge>
                        @endif
                    @endif

                    @if (! $card['isKnown'])
                        <span class="ffb-card-meta">{{ __('filament-form-builder::general.integrations.unknown_hint') }}</span>
                    @elseif (! $card['enabled'])
                        <span class="ffb-card-meta">{{ __('filament-form-builder::general.integrations.disabled') }}</span>
                    @endif

                    {{-- Only counted when the page loads; later renders leave these as they are. --}}
                    <span class="ffb-card-stats" wire:ignore>
                        @if (($stat['failed'] ?? 0) > 0)
                            <x-filament::badge color="danger" :icon="Heroicon::OutlinedExclamationTriangle" size="sm">
                                {{ trans_choice('filament-form-builder::general.integrations.failed_recently', $stat['failed'], ['count' => $stat['failed']]) }}
                            </x-filament::badge>
                        @endif

                        @if ($stat['last_ran_at'] ?? null)
                            <span class="ffb-card-meta">
                                <x-filament::icon :icon="Heroicon::OutlinedClock" />
                                {{ __('filament-form-builder::general.integrations.last_ran', ['date' => $stat['last_ran_at']->translatedFormat('j M, H:i')]) }}
                            </span>
                            <span class="ffb-card-meta">
                                <x-filament::icon :icon="Heroicon::OutlinedCheckCircle" />
                                {{ trans_choice('filament-form-builder::general.integrations.succeeded_count', $stat['succeeded'], ['count' => $stat['succeeded']]) }}
                            </span>
                        @endif
                    </span>
                </footer>
            </article>
        @endforeach

        @unless ($isDisabled || $choices === [])
            <div class="ffb-card-add">
                <span class="ffb-card-add-title">
                    <x-filament::icon :icon="Heroicon::OutlinedPlus" />
                    {{ __('filament-form-builder::general.integrations.add') }}
                </span>

                <div class="ffb-card-choices">
                    @foreach ($choices as $class => $choice)
                        <button type="button" wire:click="{{ $mount('add', ['class' => $class]) }}" class="ffb-card-choice">
                            <x-filament::icon :icon="$choice['icon']" class="ffb-card-choice-icon" />
                            <span class="ffb-card-choice-title">{{ $choice['label'] }}</span>

                            @if (filled($choice['description']))
                                <span class="ffb-card-choice-description">{{ $choice['description'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <span class="ffb-card-add-hint">{{ __('filament-form-builder::general.integrations.add_hint') }}</span>
            </div>
        @endunless
    </div>
</x-dynamic-component>
