@php
    $choices = $field->getFilterOptions();
    $default = $field->getDefaultValue();
@endphp

<div class="ffb-preview">
    @include('filament-form-builder::filament.previews.partials.label')

    <div class="ffb-preview-input ffb-preview-dropdown">
        @if (is_string($default) && isset($choices[$default]))
            <span class="ffb-preview-value">{{ $choices[$default] }}</span>
        @else
            {{ $field->placeholder ?? null }}
        @endif
    </div>
    <div class="ffb-preview-key">{{ $field->getKey() }}</div>
</div>
