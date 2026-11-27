@php
    $options = $field->options ?? [];
    $defaults = (array) ($field->getDefaultValue() ?? []);
@endphp

<div class="ffb-preview">
    @include('filament-form-builder::filament.previews.partials.label')
    @include('filament-form-builder::filament.previews.partials.description')

    <div class="ffb-preview-options">
        @forelse (array_slice($options, 0, 5) as $option)
            <div class="ffb-preview-option">
                <span @class([
                    'ffb-preview-check',
                    'ffb-preview-check-round' => ! $field::allowsMultiple(),
                    'ffb-preview-check-on' => in_array($option['value'] ?? null, $defaults, true),
                ])></span>
                {{ $option['label'] ?? $option['value'] ?? '' }}
            </div>
        @empty
            <div class="ffb-preview-muted">{{ __('filament-form-builder::general.canvas.no_options') }}</div>
        @endforelse

        @if (count($options) > 5)
            <div class="ffb-preview-muted">{{ __('filament-form-builder::general.canvas.more_options', ['count' => count($options) - 5]) }}</div>
        @endif
    </div>
    <div class="ffb-preview-key">{{ $field->getKey() }}</div>
</div>
