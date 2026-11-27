@php($value = $field->getDefaultValue())

<div class="ffb-preview ffb-preview-hidden">
    <span class="ffb-preview-label">{{ $field->getLabel() }}</span>
    <span class="ffb-preview-muted">
        @if (filled($field->queryParameter))
            {{ filled($value) && is_scalar($value)
                ? __('filament-form-builder::general.canvas.hidden_from_query_or', ['parameter' => $field->queryParameter, 'value' => $value])
                : __('filament-form-builder::general.canvas.hidden_from_query', ['parameter' => $field->queryParameter]) }}
        @else
            {{ filled($value) && is_scalar($value)
                ? __('filament-form-builder::general.canvas.hidden_value', ['value' => $value])
                : __('filament-form-builder::general.canvas.hidden_empty') }}
        @endif
    </span>
    <span class="ffb-preview-key">{{ $field->getKey() }}</span>
</div>
