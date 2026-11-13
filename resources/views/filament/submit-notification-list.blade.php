@use('Filament\Support\Icons\Heroicon')

@php
    $key = $getKey();
    $cards = $field->getCards();
    $isDisabled = $isDisabled();
    $last = array_key_last($cards);
    $first = array_key_first($cards);
    $mount = fn (string $action, array $arguments = []): ?string => $getAction($action)($arguments)->getLivewireClickHandler();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if ($cards === [])
        @unless ($isDisabled)
            <div class="ffb-outcomes-divided">
                <x-filament::link tag="button" :icon="Heroicon::Plus" wire:click="{{ $mount('add') }}">
                    {{ __('filament-form-builder::general.submit_rules.add') }}
                </x-filament::link>
            </div>
        @endunless
    @else
        <div class="ffb-cards ffb-outcomes ffb-outcomes-divided">
            <p class="ffb-outcomes-intro">{{ __('filament-form-builder::general.submit_rules.intro') }}</p>

            @foreach ($cards as $id => $card)
                <article wire:key="{{ $this->getId() }}.{{ $key }}.{{ $id }}" class="ffb-card ffb-outcome">
                    <x-filament::badge :icon="Heroicon::OutlinedFunnel" size="sm" class="ffb-outcome-when">
                        {{ $card['when'] ?? __('filament-form-builder::general.submit_rules.no_conditions') }}
                    </x-filament::badge>

                    <x-filament::icon :icon="Heroicon::ArrowRight" class="ffb-outcome-arrow" />

                    <button
                        type="button"
                        @if (! $isDisabled) wire:click="{{ $mount('edit', ['item' => $id]) }}" @endif
                        @disabled($isDisabled)
                        class="ffb-outcome-then"
                    >
                        <x-filament::icon :icon="$card['isRedirect'] ? Heroicon::OutlinedArrowTopRightOnSquare : Heroicon::OutlinedChatBubbleLeftEllipsis" class="ffb-outcome-then-icon" />
                        <span>{{ $card['then'] }}</span>
                    </button>

                    @unless ($isDisabled)
                        <x-filament::dropdown placement="bottom-end">
                            <x-slot name="trigger">
                                <x-filament::icon-button :icon="Heroicon::EllipsisVertical" color="gray" :label="__('filament-form-builder::general.notifications.actions')" />
                            </x-slot>

                            <x-filament::dropdown.list>
                                <x-filament::dropdown.list.item :icon="Heroicon::OutlinedPencilSquare" wire:click="{{ $mount('edit', ['item' => $id]) }}">
                                    {{ __('filament-form-builder::general.notifications.edit') }}
                                </x-filament::dropdown.list.item>

                                @if ($id !== $first)
                                    <x-filament::dropdown.list.item :icon="Heroicon::OutlinedArrowUp" wire:click="{{ $mount('moveUp', ['item' => $id]) }}">
                                        {{ __('filament-form-builder::general.submit_rules.move_up') }}
                                    </x-filament::dropdown.list.item>
                                @endif

                                @if ($id !== $last)
                                    <x-filament::dropdown.list.item :icon="Heroicon::OutlinedArrowDown" wire:click="{{ $mount('moveDown', ['item' => $id]) }}">
                                        {{ __('filament-form-builder::general.submit_rules.move_down') }}
                                    </x-filament::dropdown.list.item>
                                @endif

                                <x-filament::dropdown.list.item :icon="Heroicon::OutlinedDocumentDuplicate" wire:click="{{ $mount('clone', ['item' => $id]) }}">
                                    {{ __('filament-forms::components.builder.actions.clone.label') }}
                                </x-filament::dropdown.list.item>

                                <x-filament::dropdown.list.item :icon="Heroicon::OutlinedTrash" color="danger" wire:click="{{ $mount('delete', ['item' => $id]) }}">
                                    {{ __('filament-forms::components.builder.actions.delete.label') }}
                                </x-filament::dropdown.list.item>
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                    @endunless
                </article>
            @endforeach

            <p class="ffb-outcomes-otherwise">{{ $field->getOtherwise() }}</p>

            @unless ($isDisabled)
                <div>
                    <x-filament::button color="gray" size="sm" :icon="Heroicon::Plus" wire:click="{{ $mount('add') }}">
                        {{ __('filament-form-builder::general.submit_rules.add_more') }}
                    </x-filament::button>
                </div>
            @endunless
        </div>
    @endif
</x-dynamic-component>
