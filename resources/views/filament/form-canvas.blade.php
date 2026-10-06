@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\Icons\Heroicon;

    $key = $getKey();
    $livewireKey = $getLivewireKey();
    $columns = $getGridColumns();
    $items = $getItems();
    [$fixedBefore, $fixedAfter] = $getFixedFields();
    $acceptsFields = $acceptsFields();
    $editAction = $getAction('edit');
    $cloneAction = $getAction('clone');
    $deleteAction = $getAction('delete');
    $narrowAction = $getAction('narrow');
    $widenAction = $getAction('widen');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        @if ($acceptsFields)
            x-load
            x-load-src="{{ FilamentAsset::getAlpineComponentSrc('form-canvas', 'van-ons/filament-form-builder') }}"
            x-data="formCanvasComponent({ key: @js($key) })"
        @endif
        @class(['ffb-canvas', 'ffb-canvas-readonly' => ! $acceptsFields])
    >
        <div
            x-ref="grid"
            class="ffb-canvas-grid"
            style="--ffb-columns: {{ $columns }}"
        >
            @foreach ($fixedBefore as $fixedField)
                @include('filament-form-builder::filament.partials.canvas-fixed-field', ['field' => $fixedField])
            @endforeach

            @if ($acceptsFields)
                @forelse ($items as $uuid => $item)
                    {{-- A hidden field takes no room on the page, so it gets a row of its own here. --}}
                    @php($span = $item->isHidden() ? $columns : $item->getColumnSpan($columns))

                    <div
                        wire:key="{{ $livewireKey }}.items.{{ $uuid }}"
                        data-item="{{ $uuid }}"
                        @class(['ffb-canvas-item', 'ffb-canvas-item-hidden' => $item->isHidden()])
                        style="--ffb-span: {{ $span }}"
                    >
                        <div class="ffb-canvas-item-toolbar">
                            <x-filament::icon :icon="$item::icon()" class="ffb-canvas-item-icon" />
                            <span class="ffb-canvas-item-type">{{ $item::getTypeLabel() }}</span>
                            @if ($item->isHidden())
                                <x-filament::icon
                                    :icon="Heroicon::OutlinedEyeSlash"
                                    :title="__('filament-form-builder::fields.hidden_badge')"
                                    class="ffb-canvas-item-icon ffb-canvas-item-flag"
                                />
                            @endif
                            @if ($item->hasConditions())
                                <x-filament::icon
                                    :icon="Heroicon::OutlinedArrowTurnDownRight"
                                    :title="__('filament-form-builder::fields.conditions')"
                                    class="ffb-canvas-item-icon ffb-canvas-item-flag"
                                />
                            @endif
                            <span class="ffb-canvas-item-span">{{ $span }}/{{ $columns }}</span>
                            <div class="ffb-canvas-item-actions">
                                @unless ($item->isHidden())
                                    {{ $narrowAction(['item' => $uuid]) }}
                                    {{ $widenAction(['item' => $uuid]) }}
                                @endunless
                                {{ $cloneAction(['item' => $uuid]) }}
                                {{ $deleteAction(['item' => $uuid]) }}
                            </div>
                        </div>

                        <div
                            role="button"
                            tabindex="0"
                            x-on:click="edit(@js($uuid))"
                            x-on:keydown.enter="edit(@js($uuid))"
                            class="ffb-canvas-item-preview"
                        >
                            @include($item->getPreviewView(), ['field' => $item])
                        </div>
                    </div>
                @empty
                    <div class="ffb-canvas-empty">
                        {{ __('filament-form-builder::general.canvas.empty') }}
                    </div>
                @endforelse
            @endif

            @foreach ($fixedAfter as $fixedField)
                @include('filament-form-builder::filament.partials.canvas-fixed-field', ['field' => $fixedField])
            @endforeach
        </div>

        @if ($acceptsFields)
            <div class="ffb-canvas-sidebar">
                <p class="ffb-canvas-sidebar-heading">{{ __('filament-form-builder::general.canvas.fields') }}</p>

                <div x-ref="palette" wire:ignore class="ffb-canvas-palette">
                    @foreach ($getFieldTypes() as $type => $class)
                        <button
                            type="button"
                            data-type="{{ $type }}"
                            x-on:click="add(@js($type))"
                            class="ffb-canvas-palette-item"
                        >
                            <x-filament::icon :icon="$class::icon()" />
                            <span>{{ $class::getTypeLabel() }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-dynamic-component>
