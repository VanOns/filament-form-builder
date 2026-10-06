@php
    use Filament\Support\Icons\Heroicon;
    use Illuminate\Support\Js;

    $width = $getCanvasWidth($field);
    $options = $getWidthOptions($field);
    $isEditable = $uuid !== null;
    $conditions = $getConditionBadge($field);
@endphp

<div
    @if ($isEditable)
        wire:key="{{ $livewireKey }}.items.{{ $uuid }}"
        data-item="{{ $uuid }}"
        x-bind:class="{ 'ffb-canvas-item-selected': editing === @js($uuid) }"
    @endif
    data-span="{{ $width->value }}"
    data-minimum="{{ $field::minWidth()->value }}"
    @class([
        'ffb-canvas-item',
        'ffb-canvas-item-fixed' => ! $isEditable,
        'ffb-canvas-item-hidden' => $field->isHidden(),
    ])
    style="--ffb-span: {{ $width->value }}"
>
    <div class="ffb-canvas-item-toolbar">
        @if ($isEditable)
            <svg class="ffb-canvas-item-grip" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <circle cx="5.5" cy="3.5" r="1.25" /><circle cx="10.5" cy="3.5" r="1.25" />
                <circle cx="5.5" cy="8" r="1.25" /><circle cx="10.5" cy="8" r="1.25" />
                <circle cx="5.5" cy="12.5" r="1.25" /><circle cx="10.5" cy="12.5" r="1.25" />
            </svg>
        @endif
        <x-filament::icon :icon="$field::icon()" class="ffb-canvas-item-icon" />
        <span class="ffb-canvas-item-type">{{ $field::getTypeLabel() }}</span>

        @if ($field->isHidden())
            <span class="ffb-canvas-badge">
                <x-filament::icon :icon="Heroicon::OutlinedEyeSlash" class="ffb-canvas-badge-icon" />
                {{ __('filament-form-builder::fields.hidden_badge') }}
            </span>
        @endif
        @if ($conditions)
            <span class="ffb-canvas-badge ffb-canvas-badge-primary" title="{{ $conditions }}">
                <x-filament::icon :icon="Heroicon::OutlinedArrowTurnDownRight" class="ffb-canvas-badge-icon" />
                <span class="ffb-canvas-badge-text">{{ $conditions }}</span>
            </span>
        @endif
        @unless ($isEditable)
            <span class="ffb-canvas-badge" title="{{ __('filament-form-builder::general.canvas.fixed') }}">
                <x-filament::icon :icon="Heroicon::OutlinedLockClosed" class="ffb-canvas-badge-icon" />
                {{ __('filament-form-builder::general.canvas.code') }}
            </span>
        @endunless

        @if ($canResize && $isEditable && ! $field->isHidden() && count($options) > 1)
            <x-filament::dropdown placement="bottom-end">
                <x-slot name="trigger">
                    <button
                        type="button"
                        title="{{ __('filament-form-builder::general.canvas.width') }}"
                        class="ffb-canvas-width-trigger"
                    >
                        {{ $width->getLabel() }}
                        <x-filament::icon :icon="Heroicon::ChevronDown" class="ffb-canvas-width-chevron" />
                    </button>
                </x-slot>

                <x-filament::dropdown.list>
                    @foreach ($options as $option)
                        <x-filament::dropdown.list.item
                            :color="$option === $width ? 'primary' : 'gray'"
                            x-on:click="resize({{ Js::from($uuid) }}, {{ $option->value }}); close()"
                        >
                            <span class="ffb-canvas-width-option">
                                <span class="ffb-canvas-width-bar" style="--ffb-fill: {{ $option->value }}"></span>
                                <span class="ffb-canvas-width-label">{{ $option->getLabel() }}</span>
                                <span>{{ $option->getDescription() }}</span>
                            </span>
                        </x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @elseif ($canResize)
            <span class="ffb-canvas-item-span">{{ $width->getLabel() }}</span>
        @endif

        @if ($isEditable)
            <div class="ffb-canvas-item-actions">
                {{ $cloneAction(['item' => $uuid]) }}
                {{ $deleteAction(['item' => $uuid]) }}
            </div>
        @endif
    </div>

    @if ($isEditable)
        <div
            role="button"
            tabindex="0"
            x-on:click="edit(@js($uuid))"
            x-on:keydown.enter="edit(@js($uuid))"
            class="ffb-canvas-item-preview"
        >
    @else
        <div class="ffb-canvas-item-preview">
    @endif
        @include($field->isHidden() ? 'filament-form-builder::filament.previews.hidden' : $field->getPreviewView(), ['field' => $field])
    </div>
</div>
