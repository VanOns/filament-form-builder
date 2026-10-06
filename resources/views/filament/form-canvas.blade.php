@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\Icons\Heroicon;

    $key = $getKey();
    $livewireKey = $getLivewireKey();
    $columns = $getGridColumns();
    $items = $getItems();
    $editAction = $getAction('edit');
    $cloneAction = $getAction('clone');
    $deleteAction = $getAction('delete');
    $narrowAction = $getAction('narrow');
    $widenAction = $getAction('widen');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('form-canvas', 'van-ons/filament-form-builder') }}"
        x-data="formCanvasComponent({ key: @js($key) })"
        class="ffb-canvas"
    >
        <div
            x-ref="grid"
            class="ffb-canvas-grid"
            style="--ffb-columns: {{ $columns }}"
        >
            @forelse ($items as $uuid => $item)
                @php($span = $item->getColumnSpan($columns))

                <div
                    wire:key="{{ $livewireKey }}.items.{{ $uuid }}"
                    data-item="{{ $uuid }}"
                    @class(['ffb-canvas-item', 'ffb-canvas-item-hidden' => $item->isHidden()])
                    style="--ffb-span: {{ $span }}"
                >
                    <div class="ffb-canvas-item-toolbar">
                        <x-filament::icon :icon="$item::icon()" class="ffb-canvas-item-icon" />
                        <span class="ffb-canvas-item-type">{{ $item::label() }}</span>
                        @if ($item->isHidden())
                            <x-filament::icon
                                :icon="Heroicon::OutlinedEyeSlash"
                                :title="__('filament-form-builder::fields.hidden_badge')"
                                class="ffb-canvas-item-icon ffb-canvas-item-flag"
                            />
                        @endif
                        @if ($item->hasVisibilityCondition())
                            <x-filament::icon
                                :icon="Heroicon::OutlinedArrowTurnDownRight"
                                :title="__('filament-form-builder::fields.conditions')"
                                class="ffb-canvas-item-icon ffb-canvas-item-flag"
                            />
                        @endif
                        <span class="ffb-canvas-item-span">{{ $span }}/{{ $columns }}</span>
                        <div class="ffb-canvas-item-actions">
                            {{ $narrowAction(['item' => $uuid]) }}
                            {{ $widenAction(['item' => $uuid]) }}
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
        </div>

        <div class="ffb-canvas-sidebar">
            <p class="ffb-canvas-sidebar-heading">{{ __('filament-form-builder::general.canvas.fields') }}</p>

            <div x-ref="palette" wire:ignore class="ffb-canvas-palette">
                @foreach ($getFieldTypes() as $type)
                    <button
                        type="button"
                        data-type="{{ $type }}"
                        x-on:click="add(@js($type))"
                        class="ffb-canvas-palette-item"
                    >
                        <x-filament::icon :icon="$type::icon()" />
                        <span>{{ $type::label() }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</x-dynamic-component>
