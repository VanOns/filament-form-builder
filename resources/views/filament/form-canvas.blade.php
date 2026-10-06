@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\Icons\Heroicon;
    use Illuminate\Support\Js;
    use VanOns\FilamentFormBuilder\Enums\FieldWidth;
    use VanOns\FilamentFormBuilder\Enums\GridLayout;

    $key = $getKey();
    $livewireKey = $getLivewireKey();
    $items = $getItems();
    [$fixedBefore, $fixedAfter] = $getFixedFields();
    $acceptsFields = $acceptsFields();
    $widths = collect(FieldWidth::available())->mapWithKeys(fn (FieldWidth $width): array => [$width->value => $width->getLabel()])->all();
    $canResize = count($widths) > 1;
    $minimums = collect($getFieldTypes())->map(fn (string $class): int => $class::minWidth()->value)->all();
    $editAction = $getAction('edit');
    $cloneAction = $getAction('clone');
    $deleteAction = $getAction('delete');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        @if ($acceptsFields)
            x-load
            x-load-src="{{ FilamentAsset::getAlpineComponentSrc('form-canvas', 'van-ons/filament-form-builder') }}"
            x-data="formCanvasComponent({ key: @js($key), widths: @js($widths), minimums: @js($minimums) })"
        @endif
        @class(['ffb-canvas', 'ffb-canvas-readonly' => ! $acceptsFields])
    >
        <div x-ref="grid" class="ffb-canvas-grid" data-layout="{{ GridLayout::current()->value }}">
            @foreach ($fixedBefore as $fixedField)
                @include('filament-form-builder::filament.partials.canvas-fixed-field', ['field' => $fixedField])
            @endforeach

            @if ($acceptsFields)
                @forelse ($items as $uuid => $item)
                    @php($width = $getCanvasWidth($item))
                    @php($options = $getWidthOptions($item))

                    <div
                        wire:key="{{ $livewireKey }}.items.{{ $uuid }}"
                        data-item="{{ $uuid }}"
                        data-span="{{ $width->value }}"
                        data-minimum="{{ $item::minWidth()->value }}"
                        @class(['ffb-canvas-item', 'ffb-canvas-item-hidden' => $item->isHidden()])
                        style="--ffb-span: {{ $width->value }}"
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
                            @if ($canResize && ($item->isHidden() || count($options) < 2))
                                <span class="ffb-canvas-item-span">{{ $width->getLabel() }}</span>
                            @elseif ($canResize)
                                <x-filament::dropdown placement="bottom-end">
                                    <x-slot name="trigger">
                                        <button
                                            type="button"
                                            title="{{ __('filament-form-builder::general.canvas.width') }}"
                                            class="ffb-canvas-item-span ffb-canvas-width-trigger"
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
                            @endif
                            <div class="ffb-canvas-item-actions">
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
