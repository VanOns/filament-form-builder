@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\Icons\Heroicon;
    use VanOns\FilamentFormBuilder\Enums\FieldWidth;
    use VanOns\FilamentFormBuilder\Enums\GridLayout;
    use VanOns\FilamentFormBuilder\Enums\StepProgress;

    $key = $getKey();
    $livewireKey = $getLivewireKey();
    $items = $getItems();
    [$fixedBefore, $fixedAfter] = $getFixedFields();
    $hasFixedFields = $hasFixedFields();
    $acceptsFields = $acceptsFields();
    $typeKeys = $getTypeKeys();
    $conditionsEditor = $getConditionsEditor();
    $layout = GridLayout::current();
    $widths = collect(FieldWidth::available())->mapWithKeys(fn (FieldWidth $width): array => [$width->value => $width->getLabel()])->all();
    $canResize = count($widths) > 1;
    $cloneAction = $getAction('clone');
    $hasSteps = $hasSteps();
    $stepSettings = $hasSteps ? $getStepSettings() : null;
    $stepSettingsAction = $getAction('stepSettings');
    $canSetSteps = $stepSettingsAction?->isVisible() && ! $isDisabled();
    $deleteAction = $getAction('delete');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        @if ($acceptsFields)
            x-load
            x-load-src="{{ FilamentAsset::getAlpineComponentSrc('form-canvas', 'van-ons/filament-form-builder') }}"
            x-data="formCanvasComponent({ key: @js($key), widths: @js($widths) })"
            x-on:modal-closed.window="editing = null"
        @endif
        @class(['ffb-canvas', 'ffb-canvas-readonly' => ! $acceptsFields])
    >
        <div class="ffb-canvas-main">
            <div class="ffb-canvas-toolbar">
                <span class="ffb-canvas-layout">{{ __('filament-form-builder::general.canvas.layout', ['layout' => $layout->getLabel()]) }}</span>

                <div class="ffb-canvas-legend">
                    @if ($hasFixedFields)
                        <span><x-filament::icon :icon="Heroicon::OutlinedLockClosed" class="ffb-canvas-legend-icon" />{{ __('filament-form-builder::general.canvas.legend.fixed') }}</span>
                    @endif
                    <span><x-filament::icon :icon="Heroicon::OutlinedEyeSlash" class="ffb-canvas-legend-icon" />{{ __('filament-form-builder::general.canvas.legend.hidden') }}</span>
                    <span><x-filament::icon :icon="Heroicon::OutlinedArrowTurnDownRight" class="ffb-canvas-legend-icon ffb-canvas-legend-icon-primary" />{{ __('filament-form-builder::general.canvas.legend.conditions') }}</span>
                </div>
            </div>

            <div class="ffb-canvas-scroll">
                <div class="ffb-canvas-board">
                    @if ($hasSteps)
                        <div
                            @if ($canSetSteps)
                                role="button"
                                tabindex="0"
                                wire:click="{{ $stepSettingsAction->getLivewireClickHandler() }}"
                                wire:keydown.enter="{{ $stepSettingsAction->getLivewireClickHandler() }}"
                            @endif
                            @class(['ffb-canvas-item', 'ffb-canvas-item-fixed', 'ffb-canvas-start', 'ffb-canvas-start-editable' => $canSetSteps])
                            style="--ffb-span: 12"
                        >
                            <div class="ffb-canvas-item-toolbar">
                                <x-filament::icon :icon="Heroicon::OutlinedFlag" class="ffb-canvas-item-icon" />
                                <span class="ffb-canvas-item-type" title="{{ __('filament-form-builder::general.steps.start_hint') }}">{{ __('filament-form-builder::general.steps.start') }}</span>
                                <span class="ffb-canvas-badge">
                                    {{ __('filament-form-builder::general.steps.progress') }}: {{ StepProgress::from($stepSettings['progress'])->getLabel() }}
                                </span>
                            </div>
                            <div class="ffb-canvas-item-preview">
                                <div class="ffb-preview ffb-preview-step">
                                    <span class="ffb-preview-step-number" data-label="{{ __('filament-form-builder::general.steps.step') }}"></span>
                                    <span @class(['ffb-preview-heading', 'ffb-preview-muted' => blank($stepSettings['title'])])>
                                        {{ $stepSettings['title'] ?? __('filament-form-builder::general.steps.untitled') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                    @foreach ($fixedBefore as $fixedField)
                        @include('filament-form-builder::filament.partials.canvas-item', ['field' => $fixedField, 'uuid' => null])
                    @endforeach

                    @if ($acceptsFields)
                        <div @class(['ffb-canvas-zone', 'ffb-canvas-zone-marked' => $hasFixedFields])>
                            @if ($hasFixedFields)
                                <p class="ffb-canvas-zone-label">
                                    <strong>{{ __('filament-form-builder::general.canvas.zone') }}</strong>
                                    <span>{{ __('filament-form-builder::general.canvas.zone_hint') }}</span>
                                </p>
                            @endif

                            <div x-ref="grid" class="ffb-canvas-grid" data-layout="{{ $layout->value }}">
                                @forelse ($items as $uuid => $item)
                                    @include('filament-form-builder::filament.partials.canvas-item', ['field' => $item, 'uuid' => $uuid])
                                @empty
                                    <div class="ffb-canvas-empty">
                                        <span class="ffb-canvas-empty-icon">
                                            <x-filament::icon :icon="Heroicon::OutlinedPlus" />
                                        </span>
                                        <p class="ffb-canvas-empty-heading">{{ __('filament-form-builder::general.canvas.empty_heading') }}</p>
                                        <p class="ffb-canvas-empty-description">{{ __('filament-form-builder::general.canvas.empty') }}</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    @foreach ($fixedAfter as $fixedField)
                        @include('filament-form-builder::filament.partials.canvas-item', ['field' => $fixedField, 'uuid' => null])
                    @endforeach
                </div>
            </div>
        </div>

        @if ($acceptsFields)
            <aside class="ffb-canvas-sidebar" x-data="{ search: '' }" wire:ignore>
                <label class="ffb-canvas-search">
                    <x-filament::icon :icon="Heroicon::OutlinedMagnifyingGlass" class="ffb-canvas-search-icon" />
                    <input
                        type="search"
                        aria-label="{{ __('filament-form-builder::general.canvas.search') }}"
                        x-model="search"
                        placeholder="{{ __('filament-form-builder::general.canvas.search') }}"
                        class="ffb-canvas-search-input"
                    />
                </label>

                @foreach ($getPaletteGroups() as $group => $types)
                    @php($labels = collect($types)->map(fn (string $class): string => mb_strtolower($class::getTypeLabel()))->values()->all())

                    <div
                        class="ffb-canvas-palette-group"
                        x-show="! search || @js($labels).some((label) => label.includes(search.toLowerCase()))"
                    >
                        <p class="ffb-canvas-palette-heading">{{ __("filament-form-builder::general.canvas.groups.{$group}") }}</p>

                        <div class="ffb-canvas-palette" data-palette>
                            @foreach ($types as $type => $class)
                                <button
                                    type="button"
                                    data-type="{{ $type }}"
                                    data-minimum="{{ $class::minWidth()->value }}"
                                    x-on:click="add(@js($type))"
                                    x-show="! search || @js(mb_strtolower($class::getTypeLabel())).includes(search.toLowerCase())"
                                    class="ffb-canvas-palette-item"
                                >
                                    <x-filament::icon :icon="$class::icon()" class="ffb-canvas-palette-icon" />
                                    <span>{{ $class::getTypeLabel() }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <p class="ffb-canvas-palette-hint">{{ __('filament-form-builder::general.canvas.palette_hint') }}</p>
            </aside>
        @endif
    </div>
</x-dynamic-component>
