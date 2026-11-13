@use('Filament\Support\Facades\FilamentAsset')
@use('Filament\Support\Icons\Heroicon')
@use('Illuminate\Support\Js')

@php
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $fields = collect($field->getFields())
        ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
        ->values();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('recipients-input', 'van-ons/filament-form-builder') }}"
        x-data="recipientsInputComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            isMultiple: @js($isMultiple()),
            fields: @js($fields),
        })"
        x-on:focusout="leave($event)"
        data-labels="{{ json_encode((object) $field->getLabels()) }}"
        class="ffb-recipients"
    >
        <x-filament::input.wrapper :disabled="$isDisabled" :valid="! $errors->has($statePath)">
            <div wire:ignore x-on:click="$refs.input?.focus(); open()" class="ffb-recipients-control">
                <template x-for="value in values" x-bind:key="value">
                    <span
                        x-bind:class="{ 'ffb-recipient-field': isField(value) && ! isBroken(value), 'ffb-recipient-broken': isBroken(value) }"
                        class="ffb-recipient"
                    >
                        <span x-show="isField(value) && ! isBroken(value)" class="ffb-recipient-icon">
                            <x-filament::icon :icon="Heroicon::OutlinedUser" />
                        </span>
                        <span x-show="isBroken(value)" class="ffb-recipient-icon">
                            <x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" />
                        </span>
                        <span x-text="label(value)"></span>

                        @unless ($isDisabled)
                            <button
                                type="button"
                                x-on:click.stop="remove(value)"
                                x-bind:aria-label="{{ Js::from(__('filament-forms::components.tags_input.actions.delete.label')) }} + ': ' + label(value)"
                                class="ffb-recipient-remove"
                            >
                                <x-filament::icon :icon="Heroicon::XMark" />
                            </button>
                        @endunless
                    </span>
                </template>

                @unless ($isDisabled)
                    <input
                        x-ref="input"
                        x-model="query"
                        x-on:focus="open()"
                        x-on:input="open(); isInvalid = false"
                        x-on:paste="$nextTick(() => /[\s,;]/.test(query.trim()) && addTyped())"
                        x-on:keydown.enter.prevent="choose()"
                        x-on:keydown.arrow-down.prevent="move(1)"
                        x-on:keydown.arrow-up.prevent="move(-1)"
                        x-on:keydown.backspace="removeLast()"
                        x-on:keydown.escape="isOpen && ($event.stopPropagation(), close())"
                        x-on:keydown="[',', ';', ' '].includes($event.key) && split($event)"
                        x-bind:placeholder="values.length ? '' : {{ Js::from($getPlaceholder()) }}"
                        id="{{ $getId() }}"
                        type="text"
                        autocomplete="off"
                        class="ffb-recipients-input"
                    />
                @endunless
            </div>
        </x-filament::input.wrapper>

        <p wire:ignore x-show="isInvalid" x-cloak class="ffb-recipients-error">
            {{ __('filament-form-builder::general.notifications.recipients_invalid') }}
        </p>

        @unless ($isDisabled)
            <div wire:ignore x-show="isOpen" x-cloak x-on:mousedown.prevent role="listbox" class="ffb-recipients-menu">
                <template x-if="addressChoice">
                    <button
                        type="button"
                        role="option"
                        x-on:click="choose(addressChoice)"
                        x-on:mouseenter="active = choices.indexOf(addressChoice)"
                        x-bind:class="{ 'ffb-active': isActive(addressChoice) }"
                        class="ffb-recipients-option"
                    >
                        <x-filament::icon :icon="Heroicon::OutlinedEnvelope" class="ffb-recipients-option-icon" />
                        <span x-text="{{ Js::from(__('filament-form-builder::general.notifications.recipients_add')) }}.replace(':address', addressChoice.value)"></span>
                    </button>
                </template>

                <p x-show="fieldChoices.length" class="ffb-recipients-heading">
                    {{ __('filament-form-builder::general.notifications.recipients_fields') }}
                </p>

                <template x-for="choice in fieldChoices" x-bind:key="choice.value">
                    <button
                        type="button"
                        role="option"
                        x-on:click="choose(choice)"
                        x-on:mouseenter="active = choices.indexOf(choice)"
                        x-bind:class="{ 'ffb-active': isActive(choice) }"
                        class="ffb-recipients-option"
                    >
                        <x-filament::icon :icon="Heroicon::OutlinedUser" class="ffb-recipients-option-icon" />
                        <span x-text="choice.label"></span>
                    </button>
                </template>

                <p class="ffb-recipients-hint">
                    {{ $fields->isEmpty() ? __('filament-form-builder::general.notifications.recipients_hint_only') : __('filament-form-builder::general.notifications.recipients_hint') }}
                </p>
            </div>
        @endunless
    </div>
</x-dynamic-component>
